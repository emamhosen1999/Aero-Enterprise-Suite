# ZKTeco ADMS / PUSH — Device Capability Matrix

Ground truth for what the hardware supports vs. what this application implements.
Drives the biometric module's UI: an action should be offered only when the device
can actually perform it, and the capability set should come from the device, not
from our assumptions.

**Source confidence.** ZKTeco does not publish the PUSH/ADMS spec. Everything below is
consolidated from vendor-adjacent PDFs and from open-source servers that handle real
device traffic (see Sources). Rows are marked:

- `[V]` verified — our own devices demonstrably do this, or it is already working in prod here
- `[D]` documented — consistent across two or more independent implementations
- `[?]` single-source or model-dependent — probe before building UI on it

Return code `-1004` means *"command not supported on this model"*. That code is the
authoritative per-model capability signal and should be recorded, not just logged as
a generic failure.

---

## 1. Device → Server

| Direction | Endpoint / `table=` | Meaning | Ours |
|---|---|---|---|
| `[V]` | `GET /iclock/cdata?SN=&options=all&pushver=&language=&DeviceType=&PushOptionsFlag=1` | Init handshake. Device announces protocol version, model class, language. | Handled, but **every announced field is discarded** |
| `[V]` | `POST /iclock/cdata?table=ATTLOG&Stamp=` | Attendance punches, tab-separated | Handled |
| `[V]` | `POST /iclock/cdata?table=OPERLOG` | Operation/audit log, incl. `FP` enrolment lines | Handled |
| `[V]` | `POST /iclock/cdata?table=USERINFO` | Device-initiated user enrolment | Handled (token-gated) |
| `[V]` | `POST /iclock/cdata?table=templatev10` | Fingerprint template, one record per enrolled finger | Stored **per finger slot** (`USERID` + `FID`), restorable via `DATA UPDATE FINGERTMP`. See "Multi-finger capture" in §2 |
| `[V]` | `POST /iclock/cdata?table=facetmpv10` | Face template | Stored; **not** restorable — no server→device face command is established (§2) |
| `[D]` | `POST /iclock/cdata?table=BIODATA` | Unified biometric payload on newer firmware (finger/face/palm/vein) | **Skipped on purpose** — accepted, answered `OK`, never parsed as attendance and never stored. See "BIODATA" below |
| `[D]` | `POST /iclock/cdata?table=ATTPHOTO` | Capture photo attached to a punch | Not stored, but skipped safely — accepted, answered `OK`, never parsed as attendance. No longer invited either: `ATTPHOTOStamp` was removed from the handshake |
| `[D]` | `POST /iclock/cdata?table=options&c=registry` | Registration payload: `DeviceType`, `FirmVer`, `IPAddress`, `MACAddress`, `Platform` | Handled — every field stored per device; blank device columns filled, populated ones never overwritten. See "Registry" below |
| `[?]` | `table=errorlog` | Device faults | Persisted to `biometric_oper_logs` as `Device Error` (capped per push); never parsed as attendance |
| `[?]` | `table=rtlog` | Realtime event stream | Accepted, logged, dropped on purpose — it mirrors punches ATTLOG already delivers |
| `[V]` | `GET /iclock/getrequest` | Device polls for a pending command | Handled |
| `[V]` | `POST /iclock/devicecmd` | Command acknowledgement `ID=&Return=&CMD=` | Handled |

**Unhandled-table hazard — closed.** `admsPush()` used to dispatch on known `table`
values and otherwise fall through to `processAttendanceLogs()`, so a `BIODATA` or
`ATTPHOTO` push was parsed as attendance and produced a wall of "line does not match
ATTLOG format" warnings. That fallthrough is now an explicit allowlist: attendance
parsing runs only for `table=ATTLOG` and for the legacy no-`table` push, and every
other table is recorded and skipped. The untabled push deliberately keeps its old
behaviour — our MB460 sends `table=ATTLOG`, but older ZK firmware pushes punches with
no `table` at all, and dropping that shape would mean rejecting traffic that currently
succeeds.

Every skip still answers the plain-text `OK` a device expects. Per the header comment
in `routes/iclock.php`, a ZKTeco unit that receives a body it does not understand
retries the same payload forever, so "we do not store this yet" must never become
"we reject this" — that applies equally to a table we skip and to a registration we
could not parse.

### Registry (`table=options&c=registry`)

The device's own account of itself: `DeviceType`, `FirmVer`, `IPAddress`,
`MACAddress`, `Platform`. Previously discarded, which is why administrators still
typed firmware and MAC in by hand (matrix §5.5). Now every field is stored per device
in `biometric_device_capabilities` with `source = registry`.

Two rules govern the write-back to `biometric_devices`:

1. **A blank column is filled** — `DeviceType` → `model`, `IPAddress` → `ip_address`.
   A value the device sends to mean "I do not know" (`0.0.0.0`, `00:00:00:00:00:00`,
   `-`, `unknown`, …) does not count as an answer and never fills anything.
2. **A populated column is never overwritten.** `DeviceCapabilityService::recordRegistry()`
   fills blanks only. DHCP churn and firmware quirks must not let a terminal silently
   rewrite a record an administrator curated, and a stale field an admin can *see* is
   a much smaller problem than a correct one a device quietly replaced.

The consequence is that drift resolves by being **shown**, not applied. The device's
answer always lands in the capability table regardless, so `snapshot()` carries both
`identity.ip_address` (what the device says) and `identity.record_ip_address` (what we
hold), and `logRegistryDrift()` puts the same fact in the log where operations already
look. The live MB460, SN `AF6P231260266`, is exactly this case: it reports
`192.168.68.100` against a stored `192.168.1.132`, and the disagreement is now visible
in the UI instead of either being lost or being applied behind an admin's back.

Firmware inconsistency is absorbed rather than assumed away: pairs may be separated by
commas, newlines, tabs, `&` or spaces (mixed within one payload); keys may carry the
SDK's `~` prefix (`~IPAddress`), which is resolved to the documented spelling; values
may contain spaces (`FirmVer=Ver 8.0.4.6-20230217`) or padding around the `=`. A
firmware that omits `c=registry` altogether is identified by content — two or more
recognised registration keys — so the registration is not misfiled as a `GET OPTION`
reply. A body that parses to nothing is logged and dropped, and still answered `OK`.

### BIODATA — deliberately skipped, and why

`BIODATA` is in `KNOWN_UNHANDLED_TABLES`: accepted, answered `OK`, logged with a
redacted sample, and **never parsed as attendance or stored**. This is a decision, not
an omission, and it should not be "fixed" without a real payload in hand:

1. **No sample exists.** Our only ADMS unit in production (MB460, SN `AF6P231260266`)
   reports `FvFunOn=0` / `PvFunOn=0` and has never sent a BIODATA push. Every field
   mapping would be copied from third-party servers, not observed.
2. **`Type` is the whole problem.** A BIODATA row is only usable once its `Type` is
   decoded into a modality, and that mapping is model-dependent and undocumented —
   this row is marked `[D]`, not `[V]`.
3. **A mislabelled row is not inert.** `TemplateRoamingService` restores exactly the
   rows whose `template_type` is `fingerprint`, by emitting `DATA UPDATE FINGERTMP` at
   real hardware. A misfiled BIODATA row would therefore be pushed to a device as a
   fingerprint. Skipping loses data we cannot use; guessing corrupts data we can.
4. **`biometric_templates` could not hold it as-is.** `template_type` is an enum of
   fingerprint/face/palm with no vein types, and the row carries no `Valid` / `Duress`
   / `Format` / `MajorVer` columns — so even a correct decode needs a migration, which
   should be written against a real payload rather than ahead of one.

The skip is loud on purpose. `logBiodataSkip()` records the field names this firmware
actually sends, the distinct `Type` values, and the descriptor fields verbatim —
templates are reduced to a byte count, because a template *is* the biometric and
`storage/logs` has no retention policy and wider read access than the database. When a
real push finally arrives, pair those `Type` values with the modalities that device
reports in its capability snapshot (`FingerFunOn` / `FaceFunOn` / `FvFunOn` /
`PvFunOn`), and only then build storage.

### Coverage for both of the above

`tests/Feature/Biometric/AdmsRegistryIngestTest.php` pins every claim in the two
sections above: the drift case with the real addresses, blank-fill, placeholder
rejection, idempotence, each wire shape the parser has to absorb, malformed bodies
answering `OK` rather than 500, and BIODATA/ATTPHOTO/unknown tables creating neither
attendance nor att-log rows — with a companion assertion that a genuine ATTLOG push
still stages a punch, since a dispatch that swallowed attendance would otherwise
satisfy every skip test.

---

## 2. Server → Device commands

Wire format `C:<commandId>:<COMMAND>`, acked as `ID=<id>&Return=<code>&CMD=<cmd>`.

### Implemented

**"Hardware" is the column that matters.** `[V]` there means *this exact emitted
string* was sent to the production MB460 (`AF6P231260266`) and came back
`Return=0`. Everything else is inference from documentation, however long it has
been in this codebase — being long-standing is a reason not to churn a string, not
evidence that it works. On an unverified row, a `-1002` (syntax) or `-1004`
(unsupported) ack means *"our string, or this model"*, and should be reported as a
protocol gap rather than a hardware fault. The same list lives in code, as
`BiometricDeviceCommand::HARDWARE_UNVERIFIED_COMMAND_TYPES`; it is the live-probe
worklist, and an entry is deleted the day a device acks it `Return=0`.

| Command | Emitted string | Hardware | Note |
|---|---|---|---|
| `INFO` | `INFO` | `[V]` | Device's own capability/counts summary |
| `CHECK` | `CHECK` | `[V]` | Forces the device to re-verify and re-push unsent data |
| `GET_OPTION` | `GET OPTION FROM <k1>,<k2>,…` | `[V]` | Direct capability query — see the key list below |
| `SET_OPTION` | `SET OPTION <key>=<value>` | `[V]` | One key per command, deliberately not batched (a batched call returns one code, so a `-1004` would not say which key) |
| `QUERY_USERINFO` | `DATA QUERY USERINFO [PIN=<n>]` | `[V]` | Roster pull; results arrive as a `table=USERINFO` push, not in the ack |
| `CHECK_ATTLOG` | `DATA QUERY ATTLOG StartTime=…\tEndTime=…` | `[V]` | **Tab-separated, and acked `Return=0` by the MB460.** This is our only separator evidence from real hardware |
| `SET_TIME` | `SET OPTIONS DateTime=<unix>` | unverified | The one device-internal setting that predates `SET_OPTION` |
| `REBOOT` | `REBOOT` | unverified | Long-standing here; destroys nothing |
| `ADD_USER` / `UPDATE_USER` | `DATA UPDATE USERINFO PIN= Name= Card= Pri=` | unverified | **Separator-suspect — see below** |
| `DELETE_USER` | `DATA DELETE USERINFO PIN=` | unverified | Destructive: removes the user and their templates |
| `GET_USERINFO` | `DATA QUERY USERINFO [PIN=<n>]` | unverified | Legacy alias. It used to emit the bare `GET USERINFO`, which appears in no reference implementation and which at least one notes devices actively reject; the enum value is kept only because rows may already carry it |
| `CLEAR_LOG` | `CLEAR LOG` | unverified | **Destructive** — including punches not yet pushed to us |
| `CLEAR_DATA` | `CLEAR DATA` | unverified | **Destructive** — wipes users + templates |
| `CLEAR_PHOTO` `[?]` | `CLEAR PHOTO` | unverified | **Destructive.** Targeted wipe of attendance capture photos — strictly narrower than `CLEAR DATA` |
| `CLEAR_BIODATA` `[?]` | `CLEAR BIODATA` | unverified | **Destructive, and the most dangerous one here** — fingerprints can be pushed back, faces cannot, so this permanently destroys every face enrolment on the unit |
| `UPDATE_FINGERTMP` `[D]` | `DATA UPDATE FINGERTMP PIN=<n>\tFID=<0-9>\tSize=<bytes>\tValid=<0\|1>\tTMP=<base64>` | unverified | The write-back half of roaming. **Tab-separated — see below** |
| `DELETE_FINGERTMP` `[?]` | `DATA DELETE FINGERTMP PIN=<n>[\tFID=<n>]` | unverified | **Destructive on the device.** `FID` omitted means *all fingers for this PIN*; it is omitted rather than sent empty or zero, because `FID=` is a syntax error and `FID=0` would silently delete only the first finger when the caller asked for all of them |

`CLEAR PHOTO` and `CLEAR BIODATA` are `[?]` — single-source — but implemented, and
the confidence behind them is grammatical rather than empirical: `CLEAR <NOUN>` is
proven in this codebase by `CLEAR LOG` and `CLEAR DATA`, and both nouns are the
device's own (`table=ATTPHOTO`, `table=BIODATA`, §1). A single-word command also
has no field-separator risk, which is the reason these two were worth adding while
a multi-field face write was not.

**`GET OPTION` keys.** `DeviceName`, `FWVersion`, `Platform`, `IPAddress`,
`MACAddress`, `WorkCode`, `LockCount`, `UserCount`, `FPCount`, `FaceCount`,
`AttLogCount`, `TransactionCount`, `MaxUserCount`, `MaxAttLogCount`,
`MaxFingerCount`, `MaxFaceCount`. `MaxUserCount` / `MaxFingerCount` /
`MaxFaceCount` against the live counts give capacity headroom — the single most
useful thing to put in front of an administrator. `FaceCount` returning `-1004` is
also how you learn a given unit has no face engine, which should then hide
face-related UI.

### `DATA UPDATE FINGERTMP` — the separator is the whole command

```
DATA UPDATE FINGERTMP PIN=<n>\tFID=<0-9>\tSize=<bytes>\tValid=<0|1>\tTMP=<base64>
```

**Fields are separated by TAB, not space.** One space follows the verb
(`…FINGERTMP PIN=`); every separator *between fields* is `\t`. This is the single
detail worth being exact about, because getting it wrong **fails silently**. If the
device's parser splits strictly on `\t`, a space-separated
`PIN=1024 FID=3 Size=16 Valid=1 TMP=…` arrives as one field whose value is the
whole remainder: `PIN` still reads `1024` (leading digits), `FID` falls back to 0,
`Size` to 0, and `TMP` is empty — so the device acks **`Return=0`** for an
enrolment that never landed. The failure surfaces months later, during the recovery
the feature exists for.

Three independent lines of evidence, all pointing at tab, none at space:

1. The only reference implementation whose source states the string literally
   builds it tab-separated:
   `'DATA UPDATE FINGERTMP PIN=%s'."\t".'FID=%s'."\t".'Size=%d'."\t".'Valid=%s'."\t".'TMP=%s'`
   (shadow046/zkteco-adms).
2. A ZKTeco distributor's PUSH command guide writes the sibling payload command as
   `DATA UPDATE BIOPHOTO PIN=1\tContent=…`.
3. **Our own hardware.** `CHECK_ATTLOG` emits
   `DATA QUERY ATTLOG StartTime=…\tEndTime=…` and the MB460 acks it `Return=0`.
   That is the only separator evidence we have from a real device, and it says tab.

`TMP` must stay last: it is the only value that can be long, so anything a device
truncates falls off the end of the payload rather than corrupting a field the
parser needs. Whitespace is stripped from the template before it is emitted — a tab
or newline surviving inside a base64 blob would split one field into two.

The same separator reasoning applies to `DATA DELETE FINGERTMP`, and applies harder:
a delete that mis-parses its `FID` destroys the wrong finger on the device, and our
stored copy is not what is lost.

`tests/Feature/Biometric/TemplateRoamingTest.php` pins the separator as a **count**
(four tabs, three spaces) so a future edit cannot quietly reintroduce a space
between two fields.

### `DATA UPDATE USERINFO` — separator-suspect

`ADD_USER` / `UPDATE_USER` emit `DATA UPDATE USERINFO PIN= Name= Card= Pri=`,
**space-separated**, while reference implementations tab-separate USERINFO exactly
as they do FINGERTMP (`'DATA UPDATE USERINFO PIN=%s'."\t".'Name=%s'."\t"…`). By the
argument above, this string is probably wrong, and its failure mode is the same
silent one: a `Name` containing a space is the case that would expose it.

It is deliberately left alone. It is long-standing, it may be working in
production, and no device of ours has acked either form — so changing it trades a
suspect string we know for a suspect string we do not. `UPDATE_FINGERTMP` had never
been sent to anything, which is why it starts on the better-evidenced form. **Probe
`DATA UPDATE USERINFO` against real hardware before touching it**, with a name
containing a space, and record the result here.

### Multi-finger capture — the defect, and the fix

`processTemplateUpload()` captured exactly two fields, `USERID` and `TMP`, with
`/USERID=(?P<userid>\d+).*?TMP=(?P<template>[a-zA-Z0-9+\/=\s]+)/s`, and wrote them
with an `updateOrInsert` keyed on (`device_user_id`, `biometric_device_id`,
`template_type`). `FID` — the finger index the device sends in every fingerprint
push — was never looked for. **So a person's second enrolled finger overwrote their
first**, every restore landed on slot 0, and anyone who enrolled two fingers got
exactly one back.

Concretely: the production MB460 holds **26 fingerprints across 13 employees**, an
average of two per person. The roaming recovery path built to protect them could
return roughly half of them, silently, at exactly the moment somebody was relying
on it.

Two things were wrong, and both had to be fixed for either to matter:

1. **`FID` was discarded.** It is now parsed and persisted, and the write is keyed
   per finger slot. Field order is no longer assumed either — the old pattern
   required `USERID` before `TMP`, and firmware is inconsistent. `TMP` last *is*
   still assumed, because a base64 blob can contain the literal text of any other
   key, so nothing after it can be parsed safely.
2. **A push carrying more than one template produced one row.** The old regex could
   not do otherwise: `.*?TMP=` matched the first marker and the
   `[a-zA-Z0-9+/=\s]+` class then swallowed every following line — separators,
   `USERID=`, digits and all, since each of those characters is in the class — so a
   two-finger push stored one row whose template was the concatenation of the rest
   of the body. Multi-finger enrolment is precisely when a device sends several
   records at once.

Schema (migration `2026_08_05_000001`): `biometric_templates.finger_index` becomes
NOT NULL and joins a unique key over
(`biometric_device_id`, `device_user_id`, `template_type`, `finger_index`).

- **Nullable would not have worked.** MySQL and SQLite both treat NULLs in a unique
  index as always distinct, so a nullable finger index is invisible to the very
  constraint meant to stop one finger overwriting another.
- **Face and palm store `-1`** (`TemplateRoamingService::NO_FINGER_INDEX`), not 0.
  A device addresses a template by PIN + FID with *no modality in the address*, so
  the guarantee worth having is that a face can never occupy a finger's slot. `-1`
  is also unforgeable: a device-reported FID below 0 is rejected at capture.
- **A fingerprint push with no `FID` stores slot 0** — the historical behaviour,
  and the slot such a template already restored into. A device that omits the field
  must still get its template stored; on ADMS, a rejected push is retried forever.
- **Rows captured before the fix** are backfilled to slot 0 for the same reason,
  and are replaced in place the next time the device pushes that person's real
  finger 0. Pre-existing collisions are archived to `biometric_template_duplicates`
  before anything is removed, and the freshest row wins.

`TemplateRoamingService::restoreTemplatesToDevice()` therefore now queues one
`DATA UPDATE FINGERTMP` per **(user, finger)**, carrying the device's real `FID`.
`FALLBACK_FINGER_INDEX` still exists but is now the exception rather than what
every restore silently sent.

### Not implemented — face write-back

| Command | Syntax | Status |
|---|---|---|
| Face write-back `[?]` | `DATA UPDATE BIODATA Pin= No= Index= Valid= Duress= Type=9 MajorVer= MinorVer= Format= Tmp=` | **Not established. Not attempted.** |

No server→device face command is established. There is no `DATA UPDATE FACE` verb
in any source: the reference implementations this document cites implement `INFO`,
`CHECK`, `GET OPTION`, `DATA UPDATE`/`DELETE`/`QUERY USERINFO` and
`DATA UPDATE`/`QUERY FINGERTMP`, and stop there — the Go library states outright
that it ships only commands confirmed on real hardware, and face is not among them.

The one candidate above is **single-source**, from a ZKTeco distributor's command
guide, and each remaining line of evidence weakens it further:

- **`Type=9` is *visible* face** — the visible-light engine on the SpeedFace class
  of device. Our MB460 pushes `table=facetmpv10`, the older per-modality face
  table, and this server does not accept BIODATA pushes at all (§1), so we have
  never seen what an MB460 would emit in that format. Writing a `facetmpv10` blob
  into a BIODATA command is a format assumption stacked on a single-source syntax.
- **The blocker is our schema as much as the protocol, and that part is checkable
  without any hardware.** Capture keeps `USERID`, `FID` and `TMP`. `MajorVer`,
  `MinorVer`, `Format`, `Index`, `No` and `Duress` are discarded at capture and
  have no columns in `biometric_templates` — and those are precisely the fields
  that tell the device which face algorithm the blob belongs to. Even with a
  certain syntax we could not populate the command. Guessing them is guessing which
  engine should decode somebody's face.

A wrong template write fails silently (the device acks, nothing is restored, it is
discovered during a real recovery), so face rows are **listed** — the gap stays
visible in the UI — and skipped with a stated, actionable reason
(`TemplateRoamingService::FACE_REASON`). The practical consequence is recorded on
`CLEAR_BIODATA` above: wiping biometrics from a unit holding a face enrolment
destroys it permanently.

To close this properly, in order: capture the discarded descriptor fields, get one
MB460 to push `table=BIODATA`, then probe a single write against a test unit. Not
before.

### Not implemented — other

| Command | Syntax | Note |
|---|---|---|
| `AC_UNLOCK` `[?]` | | Door release, access-control models only. Deliberately not implemented — our unit has no lock relay, ADMS delivery is a 30-120 s FIFO poll with no cancel (a door that opens two minutes after the click is a physical-security hazard), and unlocking a door needs its own authorisation model, not a row in a generic command queue |
| `PutFile` / `GetFile` `[?]` | | File/firmware transfer. The downside of getting a firmware write wrong is a bricked terminal recoverable only by physical service, and nothing here needs to move files onto a device |
| `LOG` `[?]` | | Probably real, but it buys nothing: operation and error logs already arrive unprompted as `table=OPERLOG` / `table=errorlog` pushes, which this server consumes |
| `Shell <cmd>` `[D]` | | **Do not expose.** Arbitrary command execution on a terminal sitting on the office LAN; it would convert any flaw in the command queue into RCE. There is no payload validation that makes this safe, so there is no "careful" version to add later |

These four are enforced in code, not just documented: they are listed in
`BiometricDeviceCommand::DELIBERATELY_UNIMPLEMENTED` and emit `UNKNOWN`, so nothing
a caller puts in a payload can reach a device.

---

## 3. Handshake options we return

Currently emitted from `buildHandshakeOptionsBody()`:

```
ATTLOGStamp, OPERLOGStamp, errorDelay, delay,
transTimes, transFlag, encrypt, ServerVer=2.4.1, PushProtVer=<negotiated>
```

Documented keys we omit: `BIODATAStamp`, `TransInterval`, `Realtime`, `TimeZone`.

Both problems in this section are now closed:

1. **`PushProtVer` is negotiated, not asserted.** The device's announced `pushver` is
   echoed back when it is a plain dotted-numeric version; anything else — absent, a
   firmware string such as the MB460's `Ver 2.0.33S-20220623`, or a CRLF injection
   attempt — falls back to the hardcoded `2.4.1`, i.e. the body that works in
   production today. Agreeing with the device is the safe direction: claiming a
   *higher* version than a terminal speaks is what makes it expect behaviour we do
   not implement. `ServerVer` still describes this server and stays asserted.
2. **`ATTPHOTOStamp` is no longer advertised.** A `*Stamp` key is the per-table sync
   cursor that invites that push; removing it removes the invitation. `transFlag` is
   deliberately untouched — its bit ordering is single-source `[?]` and its leading
   digits enable the ATTLOG/OPERLOG/USERINFO pushes we do consume.

---

## 4. Return codes

| Code | Meaning | Ours |
|---|---|---|
| `0` | success | treated as success |
| `-1` | unsupported / no data | lumped into generic failure |
| `-2` | file error | lumped into generic failure |
| `-1002` | syntax error | lumped into generic failure |
| `-1004` | **not supported on this model** | lumped into generic failure |

`markAsExecuted()` sets `status = ($returnCode == '0') ? 'executed' : 'failed'` and keeps
the raw code, but nothing interprets it. Decoding `-1004` into a persisted per-device
capability flag is what lets the UI stop offering actions a given unit cannot perform.

---

## 4b. Device internal settings

**Current coverage: 1 setting.** The only device-internal option this application can
write is the clock, via the hardcoded `SET_TIME` special case emitting
`SET OPTIONS DateTime=<unix>`. There is no generic `SET OPTION <key>=<value>`, no
`GET OPTION` read-back, and no settings screen of any kind. The `biometric_devices.config`
JSON column is validated as an array in `store()`/`update()` but is never populated,
read, or surfaced — it is an empty placeholder, not a device config store.

Settable/readable device options, from the reverse-engineered ZK protocol reference.
**Caveat:** these key names come from the TCP/UDP SDK spec and are shared with the push
protocol's `SET OPTION`/`GET OPTION`, but push firmware supports only a subset and it
varies by model. Return code `-1004` is how a device tells you a key is unsupported,
so a settings UI must read before it writes and record what came back.

| Group | Keys |
|---|---|
| Identity (read-only) | `~SerialNumber`, `~DeviceName`, `~Platform`, `~OEMVendor`, `~ProductTime`, `MAC` |
| Biometric tuning | `FingerFunOn`, `FaceFunOn`, `~RFCardOn`, `~IsOnlyRFMachine`, `MThreshold` (1:N), `VThreshold` (1:1), `EThreshold` (enrol), `Must1To1`, `MSpeed`, `ShowScore` |
| Access control | `LockOn` (lock-open duration), `UnlockPerson`, `AntiPassbackOn`, `OnlyPINCard`, `MustEnroll` |
| Attendance rules | `WorkCode`, `AlarmReRec` (duplicate-punch minimum interval), `AlarmAttLog`, `AlarmOpLog` |
| Display / UX | `VoiceOn`, `~ShowState`, `TOState`, `TOMenu`, `NewLng` |
| Power | `IdleMinute`, `IdlePower`, `AutoPowerOn`, `AutoPowerOff`, `AutoPowerSuspend` |
| Bell / alarms | `AutoAlarm1` … `AutoAlarm6` |
| Network | `NetworkOn`, `TCPPort`, `UDPPort`, `HiSpeedNet`, `DeviceID` |
| Serial | `RS232On`, `RS485On`, `RS232BaudRate` |
| User ID format | `~PIN2Width`, `~IsABCPinEnable` |

Two that matter operationally more than the rest:

- **`AlarmReRec`** — the device-side duplicate-punch suppression window. We currently
  do duplicate rejection server-side only (`isDuplicatePunch`), so the device keeps
  sending punches we then discard. Setting this at source reduces noise.
- **`MThreshold`** — the 1:N match threshold. This is the dial for false-accept vs.
  false-reject complaints, and today it can only be changed by walking to the unit.

**Danger group.** `NetworkOn`, `TCPPort`, `UDPPort`, `DeviceID` and the power-off
schedule can strand a device: a bad value takes the unit off the network and the only
recovery is physical access. These must not sit in the same undifferentiated form as
`VoiceOn`. Gate them behind an explicit confirmation that names the risk, and never
offer them in a bulk/multi-device action.

---

## 5. Implications for the UI

1. Store a `capabilities` snapshot per device (counts, maxima, firmware, platform,
   MAC) refreshed by `INFO` + `GET OPTION`, with a "last probed" timestamp.
2. Show capacity as used/max for users, fingerprints, faces, and attendance records.
3. Drive the command dropdown from the capability snapshot; grey out with a reason
   rather than hiding, so admins understand *why* an action is unavailable.
4. Surface `-1004` in command history as "not supported on this model", not "failed".
5. ~~Registration data (`FirmVer`, `Platform`, `MACAddress`) should populate the device
   record automatically instead of being typed by an admin.~~ **Done** — see
   "Registry" in §1. One caveat the UI must honour: registration fills a *blank*
   column only, so where the device disagrees with a populated record the snapshot
   carries both `identity.ip_address` and `identity.record_ip_address`. That
   disagreement is a thing to surface to an administrator, not to reconcile silently.

---

## Sources

- [General Introduction of PUSH SDK Protocol (ZKTeco, PDF)](https://cdn.tvc.mx/media/92185/General-Introduction-of-PUSH-SDK-Protocol.pdf)
- [s0x90/zkteco-adms — Go ADMS implementation](https://github.com/s0x90/zkteco-adms)
- [saifulcoder/adms-server-ZKTeco — Postman collection of real device traffic](https://github.com/saifulcoder/adms-server-ZKTeco)
- [shashinvision/iclock — PHP iclock server](https://github.com/shashinvision/iclock)
- [ZKTeco ADMS Protocol overview](https://www.linkedin.com/pulse/zkteco-adms-protocol-link-your-zk-device-server-herbin-tsobeng-qg0ze)
- [Attendance PUSH Communication Protocol 20200325](https://www.scribd.com/document/604032067/Attendance-PUSH-Communication-Protocol-20200325)
- [ZKTeco PUSH SDK](https://www.zkteco.com/en/PUSHSDK)
