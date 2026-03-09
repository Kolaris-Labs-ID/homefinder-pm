# TODO: Finance Role Cannot See Payroll Records

## Problem

Finance users cannot see payroll records (PayrunBatch / WeeklyAllowanceBatch) in the system.

## Root Cause

The `scopeVisibleToUser()` method in `HasPayrollApproval` trait does NOT check for a generic "finance" role. It only grants access if the user is explicitly assigned as a finance approver in the active `PayrollWorkflowConfig`.

## How Visibility Works

- `super_admin`, `admin`, `hr` → see all batches (no restrictions)
- Finance approver (assigned in workflow step) → sees `pending_finance` + `paid` only
- COO approver (assigned in workflow step) → sees `pending_coo` + `paid` only
- Everyone else → sees nothing (`WHERE 1 = 0`)

## Likely Causes (check these)

- [ ] No active `PayrollWorkflowConfig` exists for the module (monthly/weekly)
- [ ] Finance workflow step is assigned to a specific user ID, not the current finance user
- [ ] Finance workflow step is assigned by role, but `assignee_role` doesn't match the actual role name (e.g. step expects `finance_approver` but user has `finance`)

## Key Files

- `app/Models/Hris/Traits/HasPayrollApproval.php` — `scopeVisibleToUser()` scope
- `app/Models/Hris/PayrollWorkflowStep.php` — `canBeActedOnBy()` check
- `app/Filament/Resources/PayrunBatchResource.php` — applies `visibleToUser` in table query
- `app/Filament/Pages/PayrollApprovalConfig.php` — UI to configure workflow steps
- `app/Services/Hris/PayrollApprovalService.php` — approval business logic

## Fix Options

1. **Config fix (no code change):** Go to Payroll Approval Config page, ensure an active workflow exists, and assign the finance step to the correct user or role name.
2. **Code fix (if finance role should always see records):** Add `'finance'` to the privileged roles list in `scopeVisibleToUser()`:
    ```php
    if ($user->hasRole(['super_admin', 'admin', 'hr', 'finance'])) {
        return $query;
    }
    ```
3. **Code fix (if finance should see limited statuses):** Add a fallback role check after the workflow step check:
    ```php
    if ($user->hasRole('finance')) {
        $visibleStatuses = array_merge($visibleStatuses, ['pending_finance', 'paid']);
    }
    ```
