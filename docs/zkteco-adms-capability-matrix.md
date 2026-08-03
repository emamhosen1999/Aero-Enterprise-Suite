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
| `[V]` | `POST /iclock/cdata?table=templatev10` | Fingerprint template | Stored, never restorable |
| `[V]` | `POST /iclock/cdata?table=facetmpv10` | Face template | Stored, never restorable |
| `[D]` | `POST /iclock/cdata?table=BIODATA` | Unified biometric payload on newer firmware (finger/face/palm/vein) | **Not handled** — falls through to the ATTLOG parser |
| `[D]` | `POST /iclock/cdata?table=ATTPHOTO` | Capture photo attached to a punch | Not handled — and no longer invited: `ATTPHOTOStamp` was removed from the handshake |
| `[D]` | `POST /iclock/cdata?table=options&c=registry` | Registration payload: `DeviceType`, `FirmVer`, `IPAddress`, `MACAddress`, `Platform` | **Not handled** |
| `[?]` | `table=errorlog` | Device faults | Persisted to `biometric_oper_logs` as `Device Error` (capped per push); never parsed as attendance |
| `[?]` | `table=rtlog` | Realtime event stream | Accepted, logged, dropped on purpose — it mirrors punches ATTLOG already delivers |
| `[V]` | `GET /iclock/getrequest` | Device polls for a pending command | Handled |
| `[V]` | `POST /iclock/devicecmd` | Command acknowledgement `ID=&Return=&CMD=` | Handled |

**Unhandled-table hazard.** `admsPush()` dispatches on known `table` values and
otherwise falls through to `processAttendanceLogs()`. A `BIODATA` or `ATTPHOTO` push
is therefore parsed as attendance and logged as malformed rows rather than being
skipped deliberately. An explicit allowlist with a recorded "unsupported table"
outcome is safer than a silent fallthrough.

---

## 2. Server → Device commands

Wire format `C:<commandId>:<COMMAND>`, acked as `ID=<id>&Return=<code>&CMD=<cmd>`.

### Implemented

| Command | Emitted string | Note |
|---|---|---|
| `REBOOT` | `REBOOT` | |
| `SET_TIME` | `SET OPTIONS DateTime=<unix>` | |
| `ADD_USER` / `UPDATE_USER` | `DATA UPDATE USERINFO PIN= Name= Card= Pri=` | |
| `DELETE_USER` | `DATA DELETE USERINFO PIN=` | |
| `CLEAR_LOG` | `CLEAR LOG` | |
| `CLEAR_DATA` | `CLEAR DATA` | destructive — wipes users + templates |
| `CHECK_ATTLOG` | `DATA QUERY ATTLOG StartTime=\tEndTime=` | |
| `GET_USERINFO` | `GET USERINFO` | **suspect — see below** |

> `GET USERINFO` does not appear in any reference implementation. The documented form
> for pulling the roster is `DATA QUERY USERINFO` (optionally `PIN=<n>` for one user),
> and at least one implementation notes devices actively reject the shorthand verbs.
> This command has no UI exposure today, so it is unlikely anyone has ever seen it run.
> Verify against hardware before trusting it.

### Not implemented — capability discovery (highest value)

| Command | Syntax | Why it matters |
|---|---|---|
| `INFO` `[D]` | `INFO` | Device returns its own capability/counts summary |
| `GET OPTION` `[D]` | `GET OPTION FROM <k1>,<k2>,…` | Direct capability query. Keys: `DeviceName`, `FWVersion`, `Platform`, `IPAddress`, `MACAddress`, `WorkCode`, `LockCount`, `UserCount`, `FPCount`, `FaceCount`, `AttLogCount`, `TransactionCount`, `MaxUserCount`, `MaxAttLogCount`, `MaxFingerCount`, `MaxFaceCount` |
| `CHECK` `[D]` | `CHECK` | Forces the device to re-verify and re-push unsent data |

`MaxUserCount` / `MaxFingerCount` / `MaxFaceCount` versus the live counts give capacity
headroom — the single most useful thing to put in front of an administrator, and
currently absent everywhere in the UI. `FaceCount` returning `-1004` is also how you
learn a given unit has no face engine, which should then hide face-related UI.

### Not implemented — other

| Command | Syntax | Note |
|---|---|---|
| `SET OPTION` `[D]` | `SET OPTION <key>=<value>` | Generalises our DateTime-only special case |
| `DATA QUERY USERINFO` `[D]` | `DATA QUERY USERINFO [PIN=<n>]` | Roster pull; results arrive as a `table=USERINFO` push, not in the ack |
| `DATA UPDATE FINGERTMP` `[D]` | `DATA UPDATE FINGERTMP PIN= FID= …` | Write a template back to a device. **Without this, biometric roaming is one-way** — we capture templates and can never restore or replicate them |
| `DATA DELETE FINGERTMP` `[?]` | | |
| `CLEAR PHOTO` / `CLEAR BIODATA` `[?]` | | Targeted wipes instead of all-or-nothing `CLEAR DATA` |
| `AC_UNLOCK` `[?]` | | Door release, access-control models only |
| `PutFile` / `GetFile` `[?]` | | File/firmware transfer |
| `LOG` `[?]` | | |
| `Shell <cmd>` `[D]` | | **Do not expose.** Arbitrary command execution on the unit |

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
5. Registration data (`FirmVer`, `Platform`, `MACAddress`) should populate the device
   record automatically instead of being typed by an admin.

---

## Sources

- [General Introduction of PUSH SDK Protocol (ZKTeco, PDF)](https://cdn.tvc.mx/media/92185/General-Introduction-of-PUSH-SDK-Protocol.pdf)
- [s0x90/zkteco-adms — Go ADMS implementation](https://github.com/s0x90/zkteco-adms)
- [saifulcoder/adms-server-ZKTeco — Postman collection of real device traffic](https://github.com/saifulcoder/adms-server-ZKTeco)
- [shashinvision/iclock — PHP iclock server](https://github.com/shashinvision/iclock)
- [ZKTeco ADMS Protocol overview](https://www.linkedin.com/pulse/zkteco-adms-protocol-link-your-zk-device-server-herbin-tsobeng-qg0ze)
- [Attendance PUSH Communication Protocol 20200325](https://www.scribd.com/document/604032067/Attendance-PUSH-Communication-Protocol-20200325)
- [ZKTeco PUSH SDK](https://www.zkteco.com/en/PUSHSDK)
