<?php

use App\Models\HRM\BiometricDeviceCommand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device capability storage for the ZKTeco ADMS/PUSH module.
 *
 * Three things land together because they are one feature:
 *
 * 1. `biometric_device_capabilities` — a narrow key/value table rather than ~40
 *    columns on biometric_devices. The ZK option namespace is large, sparse and
 *    model-dependent (see docs/zkteco-adms-capability-matrix.md §4b): most units
 *    answer a handful of keys and return -1004 for the rest, so a column-per-key
 *    schema would be mostly NULL and would need a migration every time a new
 *    firmware exposes another key.
 * 2. `biometric_devices.capabilities_probed_at` — so the UI can say "last probed
 *    3 days ago" without aggregating over the child table on every render.
 * 3. Widening `biometric_device_commands.status` to admit `unsupported`, which is
 *    what a -1004 acknowledgement now maps to instead of `failed`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_device_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->constrained('biometric_devices')->onDelete('cascade');

            // `capability_key`, not `key`: KEY is a reserved word in MySQL and this
            // table will be hit from query-builder code that does not always quote.
            $table->string('capability_key', 64);

            // Text, not a typed column: values range from "1" through a MAC address
            // to a firmware banner. Interpretation belongs in DeviceCapabilityService.
            $table->text('value')->nullable();

            // The device answered -1004/-1 for this key: it does not exist on this
            // model. Recorded rather than discarded — this is the capability signal.
            $table->boolean('is_unsupported')->default(false);

            // Where the row came from: 'info' (INFO command), 'get_option'
            // (GET OPTION FROM ...), or 'registry' (table=options&c=registry push).
            $table->string('source', 16)->default('get_option');

            $table->timestamp('probed_at')->nullable();
            $table->timestamps();

            // Re-probing must update in place, not accumulate history rows.
            $table->unique(['biometric_device_id', 'capability_key'], 'bio_dev_cap_device_key_unique');
            $table->index(['biometric_device_id', 'is_unsupported'], 'bio_dev_cap_device_unsupported_idx');
        });

        if (! Schema::hasColumn('biometric_devices', 'capabilities_probed_at')) {
            Schema::table('biometric_devices', function (Blueprint $table) {
                $table->timestamp('capabilities_probed_at')->nullable()->after('last_heartbeat_at');
            });
        }

        $this->setCommandStatuses(BiometricDeviceCommand::STATUSES);
    }

    public function down(): void
    {
        // Any row already carrying the new status has to be folded back into the
        // old vocabulary before the column can be narrowed again.
        DB::table('biometric_device_commands')
            ->where('status', BiometricDeviceCommand::STATUS_UNSUPPORTED)
            ->update(['status' => BiometricDeviceCommand::STATUS_FAILED]);

        $this->setCommandStatuses(['pending', 'sent', 'executed', 'failed']);

        if (Schema::hasColumn('biometric_devices', 'capabilities_probed_at')) {
            Schema::table('biometric_devices', function (Blueprint $table) {
                $table->dropColumn('capabilities_probed_at');
            });
        }

        Schema::dropIfExists('biometric_device_capabilities');
    }

    /**
     * `biometric_device_commands.status` was created as a MySQL ENUM, and on
     * SQLite/Postgres Laravel renders the same Blueprint as a CHECK constraint —
     * so on every driver the column actively rejects a value it has not been told
     * about. Both shapes have to be rewritten or `unsupported` fails to insert.
     *
     * @param  array<int, string>  $statuses
     */
    private function setCommandStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'mysql') {
            $list = implode(', ', array_map(fn ($status) => "'".$status."'", $statuses));
            DB::statement("ALTER TABLE `biometric_device_commands` MODIFY COLUMN `status` ENUM({$list}) NOT NULL DEFAULT 'pending'");

            return;
        }

        // Elsewhere, drop the constraint entirely by moving to a plain string.
        // The application-level whitelist is BiometricDeviceCommand::STATUSES;
        // a database CHECK that has to be rebuilt on every new status is more
        // migration risk than it buys.
        Schema::table('biometric_device_commands', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->change();
        });
    }
};
