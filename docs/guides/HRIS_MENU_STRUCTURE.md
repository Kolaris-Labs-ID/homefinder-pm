# HRIS Menu Structure

## Current Menu Organization

Your HRIS features are organized into **multiple navigation groups** for better organization:

### 1. HRIS (Phase 1 & 2)

-   Departments
-   Employees
-   Leave Requests
-   Leave Types
-   Positions
-   Attendance Logs
-   Approval Workflows

### 2. Payroll (Phase 3)

-   Salary Structures
-   Payrun Batches
-   Reimbursements

### 3. Recruitment (Phase 4)

-   Job Postings
-   Applicants
-   Interviews

### 4. Performance (Phase 4)

-   Performance Cycles
-   Performance Goals
-   Performance Reviews

### 5. Training (Phase 4)

-   Training Records

## Why Separate Groups?

This organization provides:

-   **Better UX**: Clearer categorization of features
-   **Reduced clutter**: HRIS menu doesn't become overwhelming
-   **Logical grouping**: Related features are together
-   **Scalability**: Easy to add more features to each category

## If You Want Everything Under HRIS

If you prefer all features under a single "HRIS" menu group, you need to:

### Option 1: Change Navigation Groups (Recommended Structure)

Keep the current structure but rename groups:

-   **HRIS - Core** (Phase 1 & 2)
-   **HRIS - Payroll** (Phase 3)
-   **HRIS - Recruitment** (Phase 4)
-   **HRIS - Performance** (Phase 4)
-   **HRIS - Training** (Phase 4)

### Option 2: Consolidate Everything Under HRIS

Change all resources to use `navigationGroup = 'HRIS'`:

**Phase 3 Resources:**

-   `app/Filament/Resources/SalaryStructureResource.php`
-   `app/Filament/Resources/PayrunBatchResource.php`
-   `app/Filament/Resources/ReimbursementResource.php`

**Phase 4 Resources:**

-   `app/Filament/Resources/JobPostingResource.php`
-   `app/Filament/Resources/ApplicantResource.php`
-   `app/Filament/Resources/InterviewResource.php`
-   `app/Filament/Resources/PerformanceCycleResource.php`
-   `app/Filament/Resources/PerformanceGoalResource.php`
-   `app/Filament/Resources/PerformanceReviewResource.php`
-   `app/Filament/Resources/TrainingRecordResource.php`

Change line:

```php
protected static ?string $navigationGroup = 'Payroll'; // or 'Recruitment', 'Performance', 'Training'
```

To:

```php
protected static ?string $navigationGroup = 'HRIS';
```

Then adjust `navigationSort` to control order within the HRIS group.

## Permissions Issue

Regardless of menu structure, you still need to:

1. **Generate permissions:**

    ```bash
    php artisan shield:generate --all
    ```

2. **Assign permissions to your role:**

    ```bash
    php artisan shield:super-admin
    ```

3. **Clear cache:**
    ```bash
    php artisan permission:cache-reset
    php artisan cache:clear
    ```

## Recommended Action

**I recommend keeping the current structure** (separate groups) because:

-   It's more organized and user-friendly
-   It follows best practices for complex applications
-   It's easier to navigate with many features

**Just ensure permissions are set up** so the menus appear for authorized users.

---

**Current Status:** Phase 3 & 4 menus exist but are in separate navigation groups (Payroll, Recruitment, Performance, Training) - this is intentional and recommended.

**Action Required:** Run permission generation and assignment commands to make menus visible.
