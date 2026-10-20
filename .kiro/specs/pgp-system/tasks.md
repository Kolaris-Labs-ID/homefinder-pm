# Implementation Plan: Homefinder Growth Point (PGP) System

## Overview

This implementation plan covers the PGP System feature revamp, extending the existing Training Record system with quarterly PGP tracking, reward calculation, and a dedicated dashboard. The implementation follows Laravel/Filament conventions and integrates with the existing approval workflow.

## Tasks

- [x]   1. Database schema and model updates
    - [x] 1.1 Create migration to add PGP fields to training_records table
        - Add `training_type` enum field ('free', 'paid') with default 'free'
        - Add `pgp_points` unsigned tinyint nullable field
        - Add `approved_at` timestamp nullable field
        - _Requirements: 6.1, 6.2, 6.3_

    - [x] 1.2 Update TrainingRecord model with PGP constants and methods
        - Add TRAINING_TYPE_FREE, TRAINING_TYPE_PAID constants
        - Add PGP_FREE_TRAINING (35), PGP_PAID_MIN (15), PGP_PAID_MAX (25) constants
        - Add `isPaidTraining()`, `isFreeTraining()`, `isApprovedForPgp()` methods
        - Add `scopeApprovedInQuarter()` and `scopeWithPgpPoints()` scopes
        - Update $fillable and $casts arrays
        - _Requirements: 6.1, 6.2, 6.3, 6.4_

    - [x] 1.3 Write unit tests for TrainingRecord model extensions
        - Test training type helper methods
        - Test PGP-related scopes
        - _Requirements: 6.1, 6.2, 6.3_

- [x]   2. Implement PgpService core logic
    - [x] 2.1 Create PgpService class with quarter calculation methods
        - Implement `getCurrentQuarter()` returning start, end, quarter, year
        - Implement `getQuarterForDate(Carbon $date)` for any date
        - _Requirements: 1.1_

    - [x] 2.2 Write property test for quarter determination
        - **Property 1: Quarter Determination**
        - **Validates: Requirements 1.1**

    - [x] 2.3 Implement PgpService point calculation methods
        - Implement `getQuarterlyPoints(Employee $employee)` summing current quarter points
        - Implement `getPointsForQuarter(Employee $employee, int $quarter, int $year)`
        - Implement `getQuarterlyTrainingRecords(Employee $employee)` returning filtered records
        - _Requirements: 1.2, 1.3_

    - [x] 2.4 Write property test for quarterly point filtering
        - **Property 2: Quarterly Point Filtering**
        - **Validates: Requirements 1.2, 1.3**

    - [x] 2.5 Implement PgpService training type point assignment
        - Implement `getPointsForTrainingType(string $type, ?int $customPoints)` returning 35 for free, custom for paid
        - Implement `validatePaidTrainingPoints(int $points)` checking 15-25 range
        - _Requirements: 2.1, 2.2, 2.3_

    - [x] 2.6 Write property tests for point assignment
        - **Property 3: Free Training Fixed Points**
        - **Property 4: Paid Training Validation**
        - **Validates: Requirements 2.1, 2.3**

    - [x] 2.7 Implement PgpService reward calculation
        - Implement `calculateReward(int $totalPoints)` with floor logic
        - Return 0 if < 150, else 150000 + floor((total-150)/35) \* 35000
        - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

    - [x] 2.8 Write property test for reward calculation
        - **Property 5: Reward Calculation with Floor Logic**
        - **Validates: Requirements 3.1, 3.2, 3.3, 3.4, 3.5**

    - [x] 2.9 Implement PgpService progress and award methods
        - Implement `getProgressPercentage(int $currentPoints)` capped at 100%
        - Implement `awardPoints(TrainingRecord $record, int $points)` setting pgp_points and approved_at
        - _Requirements: 5.2, 4.4, 6.5_

    - [x] 2.10 Write property tests for progress and award
        - **Property 6: Progress Percentage Calculation**
        - **Property 7: Approval Recording Completeness**
        - **Validates: Requirements 5.2, 4.4, 6.5**

- [x]   3. Checkpoint - Ensure core service tests pass
    - Ensure all tests pass, ask the user if questions arise.

- [x]   4. Update TrainingRecordResource for PGP fields
    - [x] 4.1 Add training_type field to TrainingRecordResource form
        - Add Select component for training_type with 'free' and 'paid' options
        - Make field required
        - _Requirements: 6.4_

    - [x] 4.2 Add PGP columns to TrainingRecordResource table
        - Add training_type badge column
        - Add pgp_points column (show only when approved)
        - Add approved_at date column
        - _Requirements: 6.1, 6.2, 6.3_

    - [x] 4.3 Write feature test for TrainingRecordResource PGP fields
        - Test form displays training_type field
        - Test table shows PGP columns
        - _Requirements: 6.4_

- [x]   5. Integrate PGP into ApprovalDashboard
    - [x] 5.1 Modify ApprovalDashboard to detect training record approvals
        - Add TrainingRecord to module detection in table query
        - Add training record type badge and color
        - _Requirements: 4.1_

    - [x] 5.2 Add conditional PGP input to approval form
        - For paid training: add required numeric input (min 15, max 25)
        - For free training: display info text showing 35 PGP will be auto-assigned
        - _Requirements: 4.1, 4.2, 4.3_

    - [x] 5.3 Implement PGP point assignment on approval action
        - Inject PgpService into approval action
        - Call `awardPoints()` with appropriate points on approval
        - Auto-assign 35 for free, use input value for paid
        - _Requirements: 4.3, 4.4_

    - [x] 5.4 Write feature test for approval dashboard PGP integration
        - Test PGP input appears for paid training
        - Test validation rejects values outside 15-25
        - Test free training auto-assigns 35 PGP
        - _Requirements: 4.1, 4.2, 4.3, 4.4_

- [x]   6. Checkpoint - Ensure approval integration tests pass
    - Ensure all tests pass, ask the user if questions arise.

- [x]   7. Create PgpDashboardPage
    - [x] 7.1 Create PgpDashboardPage Filament page class
        - Create `app/Filament/Pages/PgpDashboardPage.php`
        - Configure navigation (icon, label, group, sort)
        - Inject PgpService
        - _Requirements: 5.1_

    - [x] 7.2 Implement PgpDashboardPage data methods
        - Implement `getPgpSummary()` returning currentPoints, targetPoints, progressPercentage, projectedReward, quarter, year
        - Implement `getApprovedTrainings()` returning current quarter training records
        - _Requirements: 5.2, 5.3, 5.4_

    - [x] 7.3 Create PgpDashboardPage Blade view
        - Create `resources/views/filament/pages/pgp-dashboard.blade.php`
        - Add progress bar visualization toward 150 PGP target
        - Add projected reward display with Rupiah formatting
        - Add table listing approved trainings with PGP earned
        - _Requirements: 5.2, 5.3, 5.4, 5.5_

    - [x] 7.4 Write feature test for PgpDashboardPage
        - Test page renders for authenticated employee
        - Test progress bar shows correct percentage
        - Test reward displays correctly
        - Test training list shows current quarter only
        - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5_

- [x]   8. Final checkpoint - Ensure all tests pass
    - Ensure all tests pass, ask the user if questions arise.

## Notes

- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties
- Unit tests validate specific examples and edge cases
- The implementation uses Pest PHP for all testing
- PgpService follows the existing service pattern in `app/Services/Hris/`
