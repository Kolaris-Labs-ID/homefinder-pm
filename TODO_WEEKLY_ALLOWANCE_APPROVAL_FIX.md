# TODO: Weekly Allowance Approval Workflow Fix

## Problem

Weekly allowance batches are created with `draft` status but never transition to `calculated`. The approval workflow requires `calculated` status to enable the "Submit for Approval" button, so the entire approval pipeline never starts. Finance users cannot see or act on weekly allowance batches.

## Root Cause

Two places create batches but leave status as `draft`:

1. `CreateWeeklyAllowanceBatch::afterCreate()` — creates batch with `draft`, generates payments, but never updates status to `calculated`
2. `WeeklyAllowanceService::generateBatch()` — same issue

The `HasPayrollApproval` trait's `canSubmitForApproval()` checks for `status === 'calculated'`, which is never reached.

## Expected Flow

```
draft → calculated (auto) → pending_finance → paid
                                    ↓
                              pending_coo (optional escalation) → paid
                                    ↓
                              rejected → resubmit → pending_finance
```

## TODO

- [ ] **`CreateWeeklyAllowanceBatch::afterCreate()`** — After payments are generated and batch summary is updated, set `status` to `calculated`
    - File: `app/Filament/Resources/WeeklyAllowanceBatchResource/Pages/CreateWeeklyAllowanceBatch.php`
    - After the `$batch->update([...])` call, add: `$batch->update(['status' => WeeklyAllowanceBatch::STATUS_CALCULATED]);`

- [ ] **`WeeklyAllowanceService::generateBatch()`** — Same fix, set status to `calculated` after batch summary update
    - File: `app/Services/Hris/WeeklyAllowanceService.php`
    - After the batch summary `$batch->update([...])` call, add status change to `calculated`

## Already Working (no changes needed)

- Approval buttons (Submit, Approve & Pay, Escalate, Reject, Resubmit) — all wired up in `WeeklyAllowanceBatchResource` and `ViewWeeklyAllowanceBatch`
- `PayrollApprovalService` handles both weekly and monthly batches
- `HasPayrollApproval` trait with `visibleToUser` scope for role-based access
- Approval timeline/logging in the view page
- Finance/COO role checks via `PayrollWorkflowConfig` and `PayrollWorkflowStep`

## Notes

- The weekly allowance currently only picks up employees with `weekly_allowance_enabled = true` and `daily_allowance_rate > 0` in their active salary structure. If employees are missing, check those fields.
