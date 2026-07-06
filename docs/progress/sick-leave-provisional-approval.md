# Sick Leave — Provisional Approval + 48h Medical Certificate Window

Status: **plan awaiting approval — no code written yet**
Audit performed: 2026-07-06 (8-dimension parallel codebase audit + 2 supplementary lookups)

## 1. Business rules (contracted, resolved)

- Employee submits Sick Leave **without** requiring an attachment at submission time.
- HR approval uses the **existing** approval mechanism, unchanged. On final approval: `status = approved` immediately (as today). ESS, Attendance, Payroll all behave exactly as today from this point — no new intermediate top-level status.
- Additionally at approval time, if the leave type requires an attachment and none is present yet: `certificate_status = pending`, `certificate_deadline_at = approved_at + 48h`.
- Employee may upload the certificate any time before the deadline (reuses existing attachment upload infra as-is).
- HR reviews the uploaded certificate → accept (`certificate_status = verified`, `certificate_verified_at/by` set, leave/attendance/payroll unchanged) or reject (employee may resubmit until the deadline).
- **If no certificate submitted by the 48h deadline**: system auto-rejects the Sick Leave via the **existing** rejection mechanism (reusing whatever `LeaveRequest`'s reject path already does — balance release, notifications, etc.), records system-actor audit fields, and stops. Attendance and Payroll are **not** given any special-case code — they recompute automatically because `status` is no longer `approved` (existing `hasApprovedLeave()` / `classifyLeaveDays()` logic already filters on `status = approved`).
- **If the employee uploads before the deadline**, the scheduler must never auto-reject, even if HR hasn't reviewed yet — the 48h window gates *submission* only.
- **Payroll lock**: no retroactive recalculation is implemented. If a payroll batch covering the affected date is already `pending_finance`/`paid` (`isLocked() === true`) when the leave is auto-rejected, payroll for that batch is left as-is. This is a documented operational limitation, not a bug to code around.
- Notifications reuse the existing in-app `Notification` pattern only. No push wiring.

## 2. Architecture evaluation (why this shape)

- `leave_requests.status` (plain string column: draft/pending/approved/rejected/cancelled) is read directly, with a literal `status = 'approved'` filter, by **5 independent consumers** confirmed by grep:
  - `AttendanceService::hasApprovedLeave()` (app/Services/Hris/AttendanceService.php:492) — drives attendance-day computation
  - `AttendancePayrollService::classifyLeaveDays()` (app/Services/Hris/AttendancePayrollService.php:130) — drives payroll paid/unpaid day classification
  - `HRAnalyticsService::getUnpaidLeaveStats()` (app/Services/Hris/HRAnalyticsService.php:257) — HR dashboard stat
  - `TeamController` `on_leave` field (app/Http/Controllers/Api/Hris/TeamController.php:38) — mobile team dashboard
  - `CheckinTeam` seeding command (app/Console/Commands/CheckinTeam.php:34) — test utility only
- Because the design keeps `status = approved` for the entire provisional/pending-certificate period, **all 5 of these need zero code changes** for the "provisional approval behaves exactly like approval" requirement — this is the whole point of not introducing a new top-level status.
- Because the same 5 consumers filter on `status = approved`, flipping `status` to `rejected` on deadline-miss makes attendance and payroll "naturally" reclassify the day with **no new code path** — this is what makes "reuse existing behavior" possible for the rejection side too.
- Attendance is computed on-demand (`computeStatus()`), not materialized — no attendance rows need to be written or corrected when a leave converts.
- No existing "provisional / conditional / pending-verification" concept exists anywhere in the codebase today (leave or otherwise) — confirmed, so certificate tracking is new columns, not a reused abstraction.
- Audit-trail convention: there is **no** generic system-actor-safe audit log. `PayrollApprovalLog.acted_by` is a non-nullable FK to `users` (unusable for a system actor without a sentinel user). The two real precedents are: (a) `LemburAutoClose` writing directly to its own model's columns (`auto_closed` boolean + `rejection_note` text) with no separate audit table, and (b) a dedicated `Log::channel('audit')` (config/logging.php:130-135) already used by `PayrollAuditObserver` for structured business-event logging. Plan follows both: own-model boolean+reason columns, plus an `audit` channel log line.
- Scheduler precedents to clone exactly: `hris:check-expiring-certifications` (deadline-window notify) and `lembur:auto-close` (deadline-passed auto-transition, idempotent via a boolean flag).

## 3. Gap analysis

| | |
|---|---|
| **Reuse unchanged** | `ApprovalWorkflowService`, `LeaveWorkflowService`, `Approvable` trait, `ApprovalStep`/`ApprovalWorkflow`, `LeaveBalanceService`, `AttendanceService`, `AttendancePayrollService`, `PayrollCalculationService`, `CompensationResolver`, `WeeklyAllowanceService`, `LeaveAttachmentController` + its policy + its 8-test suite, `HRAnalyticsService`, `TeamController` |
| **Modify** | `LeaveRequestController::store()` (attachment optional for SICK), the approval-completion path (stamp certificate fields), `LeaveRequestResource`/`ViewLeaveRequest` (display + actions), API serialization (new fields), leave-rejection path reused (not modified) by the scheduler |
| **Extend** | 1 additive migration (7 columns), 2 new Console Commands (reminder + auto-convert), 1 new "upload/resubmit certificate" endpoint, 1 new "verify/reject certificate" Filament action, 6 new notification messages (existing pattern) |
| **Untouched** | `attendance_logs`/payroll table schemas, `employees` schema, ffhs approvals plugin (confirmed unused), mobile push infra (stays unwired), all non-Sick leave types' validation |

## 4. Dependency map / duplicate-logic audit (must-verify-first list)

Before writing code, confirm (read-only) the exact behavior of the reject path so the scheduler reuses it byte-for-byte instead of reimplementing it:

1. `Approvable::markAsRejected($reason)` (app/Traits/Hris/Approvable.php) — does `LeaveRequest` override this the way it overrides `markAsApproved()`? Does it call `LeaveBalanceService::release()`?
2. `ApprovalWorkflowService::reject()` → `markApprovableAsRejected()` (app/Services/Hris/ApprovalWorkflowService.php:313-336, ~395-409) — full body, to confirm it's the single path that must be called by the scheduler (vs. the Filament-only legacy `LeaveRequestActionHandler::reject()` which explicitly calls `release()` itself — need to know which layer owns balance release so it isn't skipped or double-applied).
3. `notifyRequestorStatusChange()` (ApprovalWorkflowService.php:417-460) — confirm it fires unconditionally on any status→rejected transition regardless of caller, so the scheduler gets a notification "for free."

No file is touched until this is confirmed — this is the first concrete step of Phase 5, not something to guess about now.

## 5. Migration (additive, nullable, reversible)

New columns on `leave_requests` only — reuses existing `status`, `rejected_at`, `rejection_reason`, `attachment_path` rather than duplicating them:

| Column | Type | Purpose |
|---|---|---|
| `certificate_status` | string(20), nullable | `pending` \| `submitted` \| `verified` \| `rejected` \| `expired`; null = not applicable (non-attachment-required leave) |
| `certificate_deadline_at` | timestamp, nullable | `approved_at + 48h`, set once at approval |
| `certificate_submitted_at` | timestamp, nullable | when employee uploads |
| `certificate_verified_at` | timestamp, nullable | when HR accepts |
| `certificate_verified_by` | unsignedBigInteger FK→users, nullable | HR actor |
| `certificate_rejection_reason` | text, nullable | HR's reason for rejecting an uploaded cert (distinct from the leave's own `rejection_reason`, which is used for the final auto-conversion reason) |
| `auto_rejected` | boolean, default false | mirrors `LemburAutoClose.auto_closed` — marks "system, not a human approver, rejected this" for reporting/audit |

No renames, no drops, no backfill required (existing rows default to null/false = "not applicable").

## 6. Implementation plan (dependency-ordered)

| # | Step | Files | Reason | Regression risk |
|---|---|---|---|---|
| 0 | Verify reject-path internals (see §4) | read-only | must know before writing the scheduler | none (no write) |
| 1 | Migration: 7 columns on `leave_requests` | 1 new migration file | foundation for everything else | low — additive only |
| 2 | Model: fillable/casts + small scopes (`scopeCertificateExpiring`, `scopeCertificateOverdue`) on `LeaveRequest`, following `TrainingRecord::scopeExpiringWithinDays()` precedent | `app/Models/Hris/LeaveRequest.php` | encapsulate the deadline queries once, reused by both new commands | low |
| 3 | Approval-completion hook: when `LeaveType.requires_attachment && !attachment_path`, set `certificate_status=pending` + `certificate_deadline_at` | wherever `LeaveRequest::markAsApproved()` currently runs (app/Models/Hris/LeaveRequest.php) | this is the one place status becomes approved — single-point hook | medium — must not affect non-attachment leave types or leaves submitted *with* a cert already |
| 4 | Relax `LeaveRequestController::store()` attachment validation for SICK-without-cert case | `app/Http/Controllers/Api/Hris/LeaveRequestController.php` | allow submission without cert | medium — must not weaken validation for any other leave type (contract: "Only Sick Leave may be submitted without an attachment") |
| 5 | Certificate upload/resubmit endpoint, reusing `LeaveAttachmentController`'s storage/MIME/policy conventions | new thin controller method or extend `LeaveRequestController` | employee-facing upload | low — additive endpoint, existing security pattern |
| 6 | HR verify/reject certificate Filament action, modeled on `LeaveRequestActionHandler` | `app/Filament/Resources/LeaveRequestResource.php` (+ new action handler method) | HR review UI | low — additive action |
| 7 | Scheduled command: `hris:leave-certificate-remind` (clone of `CheckExpiringCertifications`) | new Console Command + `routes/console.php` entry | reminder notification before deadline | low |
| 8 | Scheduled command: `hris:leave-certificate-expire` (clone of `LemburAutoClose`) — finds `certificate_status=pending`, `certificate_deadline_at < now`, `auto_rejected=false`; row-locks; calls the **verified** reject path from step 0; sets `auto_rejected=true`, `certificate_status=expired` | new Console Command + `routes/console.php` entry | the actual auto-conversion | **highest** — must be idempotent/concurrency-safe (per §4) and must not touch leaves already `submitted`/`verified` |
| 9 | 6 notification messages via existing in-app `Notification` pattern (employee: approved-provisionally, reminder, overdue/converted; HR: submitted, waiting-review) | `ApprovalWorkflowService` or a small sibling method, following `notifyRequestorStatusChange()` shape | contract requirement | low |
| 10 | Filament display additions (cert status/deadline/submitted/verified/rejection reason) | `LeaveRequestResource`, `ViewLeaveRequest` | HR visibility | low, additive |
| 11 | ESS API serialization additions (new fields only, additive) | `LeaveRequestController` response shape | mobile needs to render upload/deadline UI (separate frontend PR, out of this repo's scope but API shape must exist) | low if strictly additive |
| 12 | Tests (see §8) | `tests/Feature/Hris/*` | contract requirement | — |

## 7. Reporting/regression risk table (confirmed via targeted audit)

`HRAnalyticsService::getUnpaidLeaveStats()`, `AttendancePayrollService::classifyLeaveDays()`, `AttendanceService::hasApprovedLeave()`, `TeamController` on_leave field, `CheckinTeam` — all filter `status = approved`. **No changes needed** to any of them: during the provisional window `status` stays `approved` so they behave exactly as today; after an auto-rejection `status` becomes `rejected` so they automatically stop counting that day, which is the desired "Alpha" outcome. No dedicated leave export/PDF exists today (none found), so no export code touches this.

## 8. Test plan (new + must-stay-green)

New: sick leave submission without cert; other leave types still require cert; approval stamps deadline correctly; upload before deadline; upload after deadline is rejected by the endpoint; HR verify; HR reject-then-resubmit; scheduler skips submitted/verified leaves; scheduler converts overdue pending leaves; scheduler is idempotent on rerun; scheduler is safe under concurrent invocation (row lock); attendance shows absent after conversion; payroll classifies converted day as unpaid/absent for non-locked batches; locked-batch case leaves payroll untouched; notifications fire; authorization on upload/verify endpoints; API serialization; migration up/down.
Existing suite (2973 tests) must stay green.

## 9. Rollback strategy

Migration is purely additive — `down()` drops the 7 new columns, safe at any time (no other code reads them if rolled back first). Both new console commands are unscheduled by removing their `routes/console.php` lines — no data corruption risk since they only ever write to the new columns + reuse the existing reject path. If the feature needs to be disabled without a deploy, guard both commands behind a config flag (matching the project's precedent of feature-flagging risky automation, e.g. the geofencing dormant/config-disabled rollback).

## 10. Known limitations (documented, not coded around)

- No retroactive payroll recalculation once a batch is locked/paid — if the deadline resolves after lock, that period's payroll is not corrected automatically.
- Certificate tracking is single-file (reuses existing `attachment_path`), matching current leave-attachment behavior for all other leave types — no multi-file support.
- Mobile push stays unwired for leave notifications (pre-existing gap, out of scope).
