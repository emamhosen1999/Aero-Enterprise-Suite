<?php

namespace App\Http\Controllers\Api;

use App\Events\BiometricDeviceConnected;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBiometricDownloadSession;
use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDeviceCommand;
use App\Services\Biometric\BiometricProcessingService;
use App\Services\Biometric\DeviceCapabilityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles push events from ZKTeco biometric devices.
 * The device sends a POST with auth_token, device_serial, device_user_id, and punch_time.
 */
class BiometricWebhookController extends Controller
{
    /**
     * `table` values that carry attendance punches.
     *
     * Matched case-insensitively. NOTE the legacy no-`table` push is also routed
     * to the attendance parser — see admsPush() for why.
     */
    private const ATTENDANCE_TABLES = ['ATTLOG'];

    /**
     * Device→server tables that are documented (matrix §1) but that this
     * application has no storage for yet. They are accepted, logged with a
     * content sample, and answered `OK` — never fed to the attendance parser,
     * which would log every line as a malformed punch.
     */
    private const KNOWN_UNHANDLED_TABLES = ['BIODATA', 'ATTPHOTO', 'ERRORLOG', 'RTLOG'];

    /**
     * `table=options` carries both the registration payload (`&c=registry`) and,
     * on some firmware, the reply to `INFO` / `GET OPTION FROM …`.
     */
    private const OPTIONS_TABLE = 'OPTIONS';

    /**
     * Command types whose real result arrives as a later push rather than in the
     * acknowledgement (matrix §2).
     */
    private const CAPABILITY_COMMAND_TYPES = ['INFO', 'GET_OPTION'];

    protected BiometricProcessingService $biometricService;

    protected DeviceCapabilityService $capabilityService;

    public function __construct(
        BiometricProcessingService $biometricService,
        DeviceCapabilityService $capabilityService
    ) {
        $this->biometricService = $biometricService;
        $this->capabilityService = $capabilityService;
    }

    public function handle(Request $request)
    {
        $authToken = $request->header('X-Device-Token') ?? $request->input('auth_token');
        $serialNumber = $request->input('device_serial');
        $deviceUserId = $request->input('device_user_id');
        $punchTime = $request->input('punch_time');

        if (! $authToken || ! $serialNumber || ! $deviceUserId) {
            return response()->json(['message' => 'Missing required parameters.'], 422);
        }

        // 1. Authenticate device
        $device = $this->biometricService->authenticateDevice($serialNumber, $authToken);

        if (! $device) {
            Log::warning('Biometric webhook: unknown device or bad token', [
                'serial' => $serialNumber,
            ]);

            return response()->json(['message' => 'Unauthorized device.'], 401);
        }

        if (! $device->is_active) {
            return response()->json(['message' => 'Device is inactive.'], 403);
        }

        // 2. Update heartbeat
        $this->biometricService->updateHeartbeat($device);

        $resolvedPunchTime = $punchTime ? Carbon::parse($punchTime) : now();

        // Parse check_type
        $verifyCode = $request->input('verify_code');
        $checkType = $this->biometricService->resolveCheckType($verifyCode, $request->input('check_type', 'in'));

        // 3. Create an ATTLOG record immediately
        $attLog = $this->biometricService->createAttLog([
            'biometric_device_id' => $device->id,
            'serial_number' => $serialNumber,
            'user_pin' => $deviceUserId,
            'user_id' => null,
            'punch_time' => $resolvedPunchTime,
            'occurred_at' => now(),
            'check_type' => $checkType,
            'verify_code' => $verifyCode,
            'work_code' => $request->input('work_code'),
            'raw_data' => $request->getContent() ?: null,
            'punch_status' => 'failed',
            'punch_status_reason' => 'Pending processing',
        ]);

        // 4. Resolve user
        $resolved = $this->biometricService->resolveOrCreateUser($deviceUserId);
        $user = $resolved['user'];

        if ($resolved['is_new']) {
            $attLog->update([
                'user_id' => $user->id,
                'punch_status' => 'unknown_user',
                'punch_status_reason' => 'Auto-created as inactive placeholder. Assign attendance type to activate.',
            ]);

            Log::warning('Biometric webhook: unknown user auto-created', [
                'employee_id' => $deviceUserId,
                'new_user_id' => $user->id,
                'device_serial' => $serialNumber,
            ]);

            return response()->json([
                'message' => 'User auto-created as inactive. Assign attendance type to enable punching.',
                'user_id' => $user->id,
                'log_id' => $attLog->id,
            ], 202);
        }

        // Link the log to the resolved user
        $attLog->update(['user_id' => $user->id]);

        // 5. Validate attendance type eligibility
        $eligibility = $this->biometricService->validateAttendanceEligibility($user, $device);

        if (! $eligibility['valid']) {
            $punchStatus = ($eligibility['reason'] === 'Device not in attendance zone') ? 'wrong_device' : 'failed';
            $attLog->update([
                'punch_status' => $punchStatus,
                'punch_status_reason' => $eligibility['reason'],
            ]);
            Log::warning('Biometric webhook: attendance validation failed', [
                'user_id' => $user->id, 'device_serial' => $serialNumber, 'reason' => $eligibility['reason'],
            ]);

            $statusCode = ($punchStatus === 'wrong_device') ? 403 : 422;

            return response()->json(['message' => $eligibility['reason']], $statusCode);
        }

        // 6. Idempotency check
        if ($this->biometricService->isDuplicatePunch($user->id, $resolvedPunchTime)) {
            $attLog->update(['punch_status' => 'duplicate']);

            return response()->json(['message' => 'Duplicate punch — already recorded.'], 200);
        }

        // 7. Build synthetic request
        $syntheticRequest = $this->biometricService->buildSyntheticPunchRequest(
            $serialNumber,
            $deviceUserId,
            $resolvedPunchTime->toISOString(),
            $checkType
        );

        // 8. Process punch
        try {
            $result = $this->biometricService->processPunch($user, $syntheticRequest);

            $attLog->update([
                'punch_status' => $result['status'] === 'success' ? 'processed' : 'failed',
                'punch_status_reason' => $result['status'] === 'success' ? null : ($result['message'] ?? null),
            ]);

            Log::info('Biometric punch processed', [
                'user_id' => $user->id,
                'device_serial' => $serialNumber,
                'result_status' => $result['status'],
                'log_id' => $attLog->id,
            ]);

            return response()->json($result, $result['status'] === 'error' ? ($result['code'] ?? 422) : 200);
        } catch (\Exception $e) {
            $attLog->update([
                'punch_status' => 'failed',
                'punch_status_reason' => $e->getMessage(),
            ]);

            Log::error('Biometric webhook punch error: '.$e->getMessage(), [
                'user_id' => $user->id,
                'device_serial' => $serialNumber,
                'log_id' => $attLog->id,
            ]);

            return response()->json(['message' => 'Failed to process punch.'], 500);
        }
    }

    /**
     * Paginated ATTLOG list for admin UI.
     */
    public function attLogs(Request $request)
    {
        $perPage = (int) $request->input('perPage', 25);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search');
        $status = $request->input('status');
        $deviceId = $request->input('device_id');

        $logs = $this->biometricService->queryAttLogs($search, $status, $deviceId, $perPage, $page);
        $stats = $this->biometricService->getAttLogStats();

        return response()->json([
            'logs' => $logs,
            'stats' => $stats,
        ]);
    }

    /**
     * Heartbeat / health-check from device (optional GET endpoint).
     */
    public function heartbeat(Request $request)
    {
        $authToken = $request->header('X-Device-Token') ?? $request->input('auth_token');
        $serialNumber = $request->input('device_serial');

        if (! $authToken || ! $serialNumber) {
            return response()->json(['message' => 'Missing parameters.'], 422);
        }

        $device = $this->biometricService->authenticateDevice($serialNumber, $authToken);

        if (! $device) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $this->biometricService->updateHeartbeat($device);

        return response()->json(['message' => 'OK', 'server_time' => now()->toISOString()]);
    }

    /**
     * ZKTeco ADMS Push Protocol - Handshake (GET /iclock/cdata)
     */
    public function admsHandshake(Request $request)
    {
        Log::info('ADMS handshake received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'query' => $request->query->all(),
        ]);

        $serialNumber = $request->query('SN');

        if (! $serialNumber) {
            Log::warning('ADMS handshake: missing serial number');

            return response('ERROR', 400)->header('Content-Type', 'text/plain');
        }

        $device = $this->biometricService->findDeviceBySerial($serialNumber);

        if (! $device) {
            Log::warning('ADMS handshake: unknown device', ['serial' => $serialNumber]);

            return response('ERROR', 404)->header('Content-Type', 'text/plain');
        }

        if (! $device->is_active) {
            Log::warning('ADMS handshake: inactive device', ['serial' => $serialNumber]);

            return response('ERROR', 403)->header('Content-Type', 'text/plain');
        }

        // Update heartbeat
        $this->biometricService->updateHeartbeat($device);

        // Dispatch device connected event
        event(new BiometricDeviceConnected($device));

        // Generate session ID
        $sessionId = $this->biometricService->generateSessionId();

        // Fetch pending command
        $command = $this->biometricService->fetchNextPendingCommand($device);

        if ($command) {
            $command->markAsSent();

            Log::info('ADMS handshake: sending command', [
                'serial' => $serialNumber,
                'command_id' => $command->id,
                'command_type' => $command->command_type,
            ]);

            return new Response(
                $command->toAdmsString(),
                200,
                [
                    'SessionID' => $sessionId,
                    'Content-Type' => 'text/plain',
                ]
            );
        }

        Log::info('ADMS handshake successful', [
            'serial' => $serialNumber,
            'session_id' => $sessionId,
        ]);

        $responseBody = $this->biometricService->buildHandshakeOptionsBody($serialNumber);

        return new Response(
            $responseBody,
            200,
            [
                'SessionID' => $sessionId,
                'Content-Type' => 'text/plain',
            ]
        );
    }

    /**
     * ZKTeco ADMS Push Protocol - device→server push (POST /iclock/cdata)
     *
     * Dispatches on `?table=`. Previously every unrecognised table fell through
     * to the attendance parser, so a `BIODATA` / `ATTPHOTO` / `errorlog` /
     * `rtlog` push (matrix §1) was parsed as punches and produced a wall of
     * "line does not match ATTLOG format" warnings. That fallthrough is now an
     * explicit allowlist:
     *
     *   - attendance parsing runs ONLY for `table=ATTLOG` and for the legacy
     *     no-`table` push,
     *   - documented-but-unimplemented tables are logged with a content sample,
     *   - anything else is logged as unknown.
     *
     * Every one of those paths still answers with the plain-text `OK` the device
     * expects (see the header comment in routes/iclock.php): a ZKTeco unit that
     * gets a body it does not understand retries the same payload forever, so
     * "we do not store this yet" must never become "we reject this".
     *
     * Why the no-`table` push stays on the attendance path: our live hardware
     * sends `table=ATTLOG` (that is the form the MB460 uses, it is what every
     * existing ADMS test asserts, and every ADMS push captured in
     * storage/logs/*.log carries an explicit `table`), but older ZK firmware
     * pushes punches to /iclock/cdata with no `table` at all, and the previous
     * code accepted that shape. Dropping it would be exactly the "start
     * rejecting traffic that currently succeeds" failure mode, so the untabled
     * push keeps its existing behaviour.
     */
    public function admsPush(Request $request)
    {
        Log::info('ADMS push received', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'query' => $request->query->all(),
            'content_length' => strlen($request->getContent()),
        ]);

        $rawData = $request->getContent();
        $table = $request->query('table');
        $serialNumber = $this->biometricService->getSerialNumber($request);

        if (! $serialNumber) {
            Log::warning('ADMS push: missing serial number');

            return response('ERROR', 400)->header('Content-Type', 'text/plain');
        }

        $device = $this->biometricService->findDeviceBySerial($serialNumber);

        if (! $device || ! $device->is_active) {
            Log::warning('ADMS push: unknown or inactive device', ['serial' => $serialNumber]);

            return response('ERROR', 401)->header('Content-Type', 'text/plain');
        }

        $normalizedTable = strtoupper(trim((string) $table));

        // Handle Biometric Template Uploads (Roaming)
        if ($normalizedTable === 'TEMPLATEV10' || $normalizedTable === 'FACETMPV10') {
            // Canonical lower-case form: processTemplateUpload() distinguishes
            // fingerprint from face by comparing against 'templatev10'.
            $result = $this->biometricService->processTemplateUpload(
                $rawData,
                strtolower($normalizedTable),
                $serialNumber,
                $device
            );

            return $result['success']
                ? new Response('OK', 200, ['Content-Type' => 'text/plain'])
                : response('ERROR', $result['reason'] === 'invalid_format' ? 400 : 500)->header('Content-Type', 'text/plain');
        }

        // Handle User Enrollment from Device. This is also where the result of a
        // `DATA QUERY USERINFO` (QUERY_USERINFO) command lands: per matrix §2 the
        // roster arrives as a table=USERINFO push, not in the acknowledgement, so
        // the existing enrollment path is already the right handler for it.
        if ($normalizedTable === 'USERINFO') {
            // Defense in depth: EnsureAdmsDeviceAuthorized already gates USERINFO,
            // but re-check here so device-initiated enrollment can never create or
            // rewrite users without a verified per-device token even if the route
            // middleware is ever detached. USERINFO is far higher risk than a punch.
            if (config('attendance.adms_enrollment_requires_token', true)
                && ! $request->attributes->get('adms_token_verified', false)) {
                Log::warning('ADMS push: USERINFO enrollment blocked (device token not verified)', [
                    'serial' => $serialNumber,
                    'device_id' => $device->id,
                ]);

                return response('ERROR', 401)->header('Content-Type', 'text/plain');
            }

            $result = $this->biometricService->processUserEnrollment($rawData, $serialNumber, $device);

            return $result['success']
                ? new Response('OK', 200, ['Content-Type' => 'text/plain'])
                : response('ERROR', $result['reason'] === 'invalid_format' ? 400 : 500)->header('Content-Type', 'text/plain');
        }

        // OPERLOG
        if ($normalizedTable === 'OPERLOG') {
            $this->biometricService->storeOperLog($rawData, $serialNumber, $device);

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        }

        // Registration payload and capability replies (matrix §1 / §2).
        if ($normalizedTable === self::OPTIONS_TABLE) {
            $this->handleOptionsPush($request, $device, $serialNumber, $rawData);

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        }

        // Command Acknowledgment
        if ($this->biometricService->isCommandAcknowledgment($rawData)) {
            $this->biometricService->processInlineAcknowledgment($rawData, $request);
            $this->processCapabilityAck($device, $rawData, $serialNumber);

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        }

        // Attendance: the real ATTLOG case, plus the legacy untabled push.
        if ($normalizedTable === '' || in_array($normalizedTable, self::ATTENDANCE_TABLES, true)) {
            // Some firmware answers INFO / GET OPTION with an untabled push
            // instead of an ack. Only divert when the body cannot be a punch
            // (no tabs, key=value shaped) AND we actually have a probe in flight
            // for this device, so a genuine punch is never stolen from ATTLOG.
            if ($normalizedTable === '' && ($command = $this->pendingCapabilityCommand($device, $rawData))) {
                $this->applyCapabilityPayload($device, $command, $rawData, $serialNumber, 'untabled-push');

                return new Response('OK', 200, ['Content-Type' => 'text/plain']);
            }

            $this->biometricService->processAttendanceLogs($rawData, $device, $serialNumber);

            return new Response('OK', 200, ['Content-Type' => 'text/plain']);
        }

        // Everything else: recorded, not parsed. Answered OK so the device does
        // not retry the same payload forever.
        $known = in_array($normalizedTable, self::KNOWN_UNHANDLED_TABLES, true);

        Log::warning(
            $known
                ? 'ADMS push: table is documented but not handled — skipped (not parsed as attendance)'
                : 'ADMS push: unknown table — skipped (not parsed as attendance)',
            [
                'serial' => $serialNumber,
                'device_id' => $device->id,
                'table' => $table,
                'content_length' => strlen($rawData),
                'sample' => $this->contentSample($rawData),
            ]
        );

        return new Response(
            'OK',
            200,
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * `POST /iclock/cdata?table=options` — two distinct payloads share one table.
     *
     *  - `&c=registry` (matrix §1): the registration blob carrying DeviceType,
     *    FirmVer, IPAddress, MACAddress, Platform. Previously ignored entirely,
     *    which is why admins still type firmware and MAC in by hand.
     *  - anything else: the reply to an `INFO` / `GET OPTION FROM …` command.
     */
    private function handleOptionsPush(Request $request, BiometricDevice $device, string $serialNumber, string $rawData): void
    {
        $isRegistry = strcasecmp((string) $request->query('c', ''), 'registry') === 0;

        // Some firmware omits `c=registry` and is identifiable only by content.
        if (! $isRegistry) {
            $fields = $this->parseKeyValueFields($rawData);
            $isRegistry = count(array_intersect(
                array_map('strtolower', array_keys($fields)),
                array_map('strtolower', DeviceCapabilityService::REGISTRY_KEYS)
            )) >= 2;
        } else {
            $fields = $this->parseKeyValueFields($rawData);
        }

        if ($isRegistry) {
            Log::info('ADMS push: device registration payload', [
                'serial' => $serialNumber,
                'device_id' => $device->id,
                'fields' => array_keys($fields),
            ]);

            $this->capabilityService->recordRegistry($device, $fields);

            return;
        }

        $command = $this->pendingCapabilityCommand($device, $rawData, false);

        $this->applyCapabilityPayload($device, $command, $rawData, $serialNumber, 'table=options');
    }

    /**
     * Hand a capability payload to the right parser.
     *
     * The correlated command decides which parser runs; with no correlation the
     * payload is treated as a GET OPTION reply, because that is the key=value
     * shape and DeviceCapabilityService stores what it recognises and logs the
     * rest rather than throwing.
     */
    private function applyCapabilityPayload(
        BiometricDevice $device,
        ?BiometricDeviceCommand $command,
        string $rawData,
        string $serialNumber,
        string $via
    ): void {
        $isInfo = $command && $command->command_type === 'INFO';

        Log::info('ADMS push: capability reply received', [
            'serial' => $serialNumber,
            'device_id' => $device->id,
            'via' => $via,
            'command_id' => $command?->id,
            'command_type' => $command?->command_type,
            'parser' => $isInfo ? 'info' : 'option',
            'sample' => $this->contentSample($rawData),
        ]);

        $isInfo
            ? $this->capabilityService->parseInfoResponse($device, $rawData)
            : $this->capabilityService->parseOptionResponse($device, $rawData);
    }

    /**
     * The INFO / GET_OPTION command a pushed reply most plausibly belongs to.
     *
     * ADMS gives a pushed result no command id at all, so correlation is
     * "the most recently sent probe for this device" — the same
     * newest-command-wins assumption the queue already relies on by handing out
     * exactly one command per poll.
     *
     * @param  bool  $requireBodyShape  when true (untabled push), also insist the
     *                                  body cannot be an attendance record.
     */
    private function pendingCapabilityCommand(BiometricDevice $device, string $rawData, bool $requireBodyShape = true): ?BiometricDeviceCommand
    {
        if ($requireBodyShape && ! $this->looksLikeOptionPayload($rawData)) {
            return null;
        }

        return BiometricDeviceCommand::where('biometric_device_id', $device->id)
            ->whereIn('command_type', self::CAPABILITY_COMMAND_TYPES)
            ->whereIn('status', [BiometricDeviceCommand::STATUS_SENT, BiometricDeviceCommand::STATUS_EXECUTED])
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Could this body be a key=value option reply rather than a punch?
     *
     * ATTLOG lines are tab-separated and contain no `=`, so requiring both
     * "no tabs" and "at least one key=value pair" keeps attendance untouched.
     */
    private function looksLikeOptionPayload(string $rawData): bool
    {
        $trimmed = trim($rawData);

        if ($trimmed === '' || str_contains($trimmed, "\t")) {
            return false;
        }

        return (bool) preg_match('/^[A-Za-z0-9_~.\-]+=/m', $trimmed);
    }

    /**
     * Post-process a command acknowledgement for capability purposes.
     *
     * Runs *after* the existing ack handling (which owns status/return-code
     * bookkeeping) and adds the two things the matrix asks for:
     *
     *  - a non-zero Return is offered to markUnsupported(), so -1004 becomes a
     *    recorded per-device capability instead of a generic failure,
     *  - an INFO / GET OPTION ack that carries its payload inline gets that
     *    payload parsed.
     */
    private function processCapabilityAck(BiometricDevice $device, string $rawData, string $serialNumber): void
    {
        $ack = $this->parseAckFields($rawData);

        if (! isset($ack['ID'])) {
            return;
        }

        $command = BiometricDeviceCommand::where('id', $ack['ID'])
            ->where('biometric_device_id', $device->id)
            ->first();

        if (! $command) {
            return;
        }

        $returnCode = (string) ($ack['Return'] ?? '');

        if ($returnCode !== '' && $returnCode !== '0') {
            $this->capabilityService->markUnsupported($command, $returnCode);

            return;
        }

        if (! in_array($command->command_type, self::CAPABILITY_COMMAND_TYPES, true)) {
            return;
        }

        $payload = $this->stripAckFields($rawData);

        if ($payload === '') {
            // Result will arrive as its own push (matrix §2) — nothing to do yet.
            return;
        }

        $this->applyCapabilityPayload($device, $command, $payload, $serialNumber, 'inline-ack');
    }

    /**
     * Parse `ID=…&Return=…&CMD=…`, tolerating the tab/newline separators real
     * devices use. Mirrors BiometricProcessingService::processInlineAcknowledgment().
     *
     * @return array<string, string>
     */
    private function parseAckFields(string $rawData): array
    {
        $normalized = str_replace(["\r\n", "\r", "\n", "\t"], '&', $rawData);
        parse_str($normalized, $fields);

        return array_filter($fields, 'is_string');
    }

    /**
     * Remove the acknowledgement envelope so only the device's own payload is
     * handed to the capability parsers.
     */
    private function stripAckFields(string $rawData): string
    {
        $stripped = preg_replace('/\b(ID|Return|CMD)=[^\r\n\t&]*/i', '', $rawData) ?? '';

        return trim(preg_replace('/[&\t ]{2,}/', ' ', $stripped) ?? '');
    }

    /**
     * Split a device payload into `key => value` pairs.
     *
     * Registration payloads arrive with the pairs separated by newlines, commas,
     * tabs or `&` depending on firmware, and values may contain `=`
     * (base64/banners) so only the first `=` splits.
     *
     * @return array<string, string>
     */
    private function parseKeyValueFields(string $rawData): array
    {
        $fields = [];

        foreach (preg_split('/[\r\n\t,&]+/', $rawData) ?: [] as $token) {
            $token = trim($token);

            if ($token === '' || ! str_contains($token, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $token, 2);
            $key = trim($key);

            if ($key === '' || mb_strlen($key) > 64) {
                continue;
            }

            $fields[$key] = trim($value);
        }

        return $fields;
    }

    /**
     * A short, log-safe excerpt of a device payload.
     */
    private function contentSample(string $rawData, int $length = 300): string
    {
        $sample = trim(preg_replace('/\s+/', ' ', $rawData) ?? '');

        return mb_substr($sample, 0, $length);
    }

    /**
     * Queue a command for a biometric device
     *
     * INFO / CHECK / GET_OPTION / SET_OPTION / QUERY_USERINFO are the capability
     * and settings verbs from matrix §2. SET_OPTION is the only one that can
     * change the device, and the only one that can strand it, so it carries the
     * extra guard in guardOptionCommand().
     */
    public function queueCommand(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|exists:biometric_devices,id',
            'command_type' => 'required|in:REBOOT,SET_TIME,ADD_USER,UPDATE_USER,DELETE_USER,CLEAR_LOG,CLEAR_DATA,GET_USERINFO,CHECK_ATTLOG,INFO,CHECK,GET_OPTION,SET_OPTION,QUERY_USERINFO',
            'payload' => 'nullable|array',
            'scheduled_at' => 'nullable|date',
            // Explicit acknowledgement that a strand-the-device key is being
            // written. Never defaulted true, never inferred.
            'confirm_dangerous' => 'sometimes|boolean',
        ]);

        if ($error = $this->guardOptionCommand($validated['command_type'], $validated['payload'] ?? [], $request->boolean('confirm_dangerous'))) {
            return response()->json($error, 422);
        }

        $device = BiometricDevice::find($validated['device_id']);

        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        if ($device->protocol !== 'adms') {
            return response()->json(['message' => 'Commands only supported for ADMS protocol devices'], 400);
        }

        if ($validated['command_type'] === 'CHECK_ATTLOG') {
            $session = $this->biometricService->initiateLogDownload(
                $device,
                'manual',
                auth()->id() ?? $request->user()?->id,
                $validated['payload'] ?? null
            );

            if ($session) {
                // Also dispatch the monitoring job to prevent stuck sessions
                ProcessBiometricDownloadSession::dispatch($session);
                $command = $session->command;
            } else {
                return response()->json(['message' => 'Failed to initiate log download session.'], 500);
            }
        } else {
            $command = $this->biometricService->createCommand($device, $validated);
        }

        return response()->json([
            'message' => $command->isScheduled() ? 'Command scheduled successfully' : 'Command queued successfully',
            'command' => [
                'id' => $command->id,
                'type' => $command->command_type,
                'status' => $command->status,
                'scheduled_at' => $command->scheduled_at,
                'adms_string' => $command->toAdmsString(),
            ],
        ]);
    }

    /**
     * Validate the payload of an option command before it is queued.
     *
     * SET_OPTION is the dangerous one (matrix §4b): `NetworkOn`, `TCPPort`,
     * `UDPPort`, `DeviceID` and the auto-power-off schedule can take a unit off
     * a customer LAN where the only recovery is walking to it. Two rules follow:
     *
     *  1. the key MUST be one the catalogue knows — user input never reaches the
     *     wire verbatim, which also removes the injection surface on a protocol
     *     that has no quoting,
     *  2. a key the catalogue marks dangerous requires `confirm_dangerous=true`
     *     in this specific request. A flag, not a config toggle and not a user
     *     permission: the confirmation has to be per-write, so a UI has to put
     *     the risk in front of the operator every single time.
     *
     * GET_OPTION is read-only, so its keys are only syntax-checked — but they
     * are still checked, because they are interpolated into the command string.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null error body, or null when acceptable
     */
    private function guardOptionCommand(string $commandType, array $payload, bool $confirmDangerous): ?array
    {
        if ($commandType === 'GET_OPTION') {
            $keys = $payload['keys'] ?? $payload['options'] ?? [];
            $keys = is_string($keys) ? explode(',', $keys) : (is_array($keys) ? $keys : []);

            foreach ($keys as $key) {
                if (! preg_match('/^[A-Za-z0-9_~.\-]{1,64}$/', trim((string) $key))) {
                    return [
                        'message' => 'Invalid option key in GET_OPTION payload.',
                        'invalid_key' => (string) $key,
                    ];
                }
            }

            return null;
        }

        if ($commandType !== 'SET_OPTION') {
            return null;
        }

        $key = trim((string) ($payload['key'] ?? ''));
        $catalogue = DeviceCapabilityService::SETTINGS_CATALOGUE;

        if ($key === '' || ! array_key_exists($key, $catalogue)) {
            return [
                'message' => $key === ''
                    ? 'SET_OPTION requires payload.key.'
                    : "Unknown device setting '{$key}'. Only keys in the settings catalogue can be written.",
                'invalid_key' => $key,
            ];
        }

        $value = $payload['value'] ?? null;

        if (! is_scalar($value) || preg_match('/[\r\n\t]/', (string) $value)) {
            return [
                'message' => "Invalid value for device setting '{$key}'.",
                'invalid_key' => $key,
            ];
        }

        if ($this->isDangerousKey($key) && ! $confirmDangerous) {
            return [
                'message' => "'{$key}' can make this device unreachable — recovery requires physical access to the unit. Re-send with confirm_dangerous=true to proceed.",
                'requires_confirmation' => true,
                'dangerous_keys' => [$key],
                'help' => $catalogue[$key]['help'] ?? null,
            ];
        }

        return null;
    }

    /**
     * Does the catalogue flag this key as able to strand the device?
     */
    private function isDangerousKey(string $key): bool
    {
        $meta = DeviceCapabilityService::SETTINGS_CATALOGUE[$key] ?? [];

        // Accept either spelling so this does not silently degrade to "nothing
        // is dangerous" if the catalogue's flag is renamed.
        return (bool) ($meta['dangerous'] ?? $meta['danger'] ?? false);
    }

    /**
     * Get command history for a device
     */
    public function getCommands(Request $request, $deviceId)
    {
        $device = BiometricDevice::find($deviceId);

        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $commandsArray = $this->biometricService->getCommandHistory((int) $deviceId);

        return response()->json(['commands' => $commandsArray]);
    }

    /**
     * Get ADMS operation logs for monitoring.
     */
    public function getLogs(Request $request, $deviceId = null)
    {
        $query = DB::table('biometric_oper_logs')
            ->orderBy('occurred_at', 'desc')
            ->limit(100);

        if ($deviceId) {
            $query->where('biometric_device_id', $deviceId);
        }

        $logs = $query->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'level' => 'info',
                'serial_number' => $log->serial_number,
                'operation_type' => $log->operation_type,
                'user_pin' => $log->user_pin,
                'raw_data' => $log->raw_data,
                'context' => json_decode($log->context ?? '[]', true),
                'created_at' => $log->occurred_at,
            ];
        });

        return response()->json(['logs' => $logs]);
    }

    /**
     * ADMS Get Request - Device requests pending commands
     */
    public function admsGetRequest(Request $request)
    {
        $serialNumber = $request->query('SN');

        if (! $serialNumber) {
            Log::warning('ADMS getrequest: missing serial number');

            return response('ERROR', 400)->header('Content-Type', 'text/plain');
        }

        $device = $this->biometricService->findDeviceBySerial($serialNumber);

        if (! $device) {
            Log::warning('ADMS getrequest: unknown device', ['serial' => $serialNumber]);

            return response('ERROR', 404)->header('Content-Type', 'text/plain');
        }

        if (! $device->is_active) {
            Log::warning('ADMS getrequest: inactive device', ['serial' => $serialNumber]);

            return response('ERROR', 403)->header('Content-Type', 'text/plain');
        }

        $this->biometricService->updateHeartbeat($device);

        $command = $this->biometricService->fetchNextPendingCommand($device);

        if ($command) {
            $command->markAsSent();

            Log::info('ADMS getrequest: sending command', [
                'serial' => $serialNumber,
                'command_id' => $command->id,
                'command_type' => $command->command_type,
            ]);

            return new Response(
                $command->toAdmsString(),
                200,
                ['Content-Type' => 'text/plain']
            );
        }

        Log::info('ADMS getrequest: no pending commands', ['serial' => $serialNumber]);

        return new Response(
            'OK',
            200,
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * ADMS Device Command - Process command acknowledgment from device
     */
    public function admsDeviceCmd(Request $request)
    {
        $serialNumber = $this->biometricService->getSerialNumber($request);
        $rawData = $request->getContent();

        if (! $serialNumber) {
            Log::warning('ADMS devicecmd: missing serial number');

            return response('ERROR', 400)->header('Content-Type', 'text/plain');
        }

        $device = $this->biometricService->findDeviceBySerial($serialNumber);

        if (! $device) {
            Log::warning('ADMS devicecmd: unknown device', ['serial' => $serialNumber]);

            return response('ERROR', 404)->header('Content-Type', 'text/plain');
        }

        $this->biometricService->acknowledgeCommand($rawData, $device, $serialNumber);

        // Capability bookkeeping on top of the existing ack handling: record
        // -1004/-1 as "this model cannot do that", and parse an INFO/GET OPTION
        // payload if the device inlined it in the acknowledgement body.
        $this->processCapabilityAck($device, $rawData, $serialNumber);

        $this->biometricService->updateHeartbeat($device);

        return new Response(
            'OK',
            200,
            ['Content-Type' => 'text/plain']
        );
    }

    /**
     * ADMS Test - Simple connectivity test endpoint
     */
    public function admsTest(Request $request)
    {
        $serialNumber = $request->query('SN');

        Log::info('ADMS test endpoint hit', ['serial' => $serialNumber]);

        return new Response(
            'OK',
            200,
            ['Content-Type' => 'text/plain']
        );
    }
}
