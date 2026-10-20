# Implementation Plan: Sudden Client Visit Logic

## Overview

Implement automatic classification of client visits as "Planned" or "Sudden", a Filament admin resource for managing client visits, and an observer that updates attendance records when a same-day visit is created. The implementation follows existing Laravel/Filament patterns in the codebase.

## Tasks

- [x]   1. Database migration and ClientVisit model updates
    - [x] 1.1 Create migration to add `visit_type` column to `client_visits` table
        - Add string column `visit_type` with default `'planned'`, placed after `status`
        - Migration file: `database/migrations/xxxx_xx_xx_add_visit_type_to_client_visits_table.php`
        - _Requirements: 1.1_

    - [x] 1.2 Update ClientVisit model with visit type constants and fillable
        - Add `TYPE_PLANNED = 'planned'` and `TYPE_SUDDEN = 'sudden'` constants
        - Add `'visit_type'` to the `$fillable` array
        - Add `getVisitTypeLabels()` static method following the existing `getStatusLabels()` pattern
        - File: `app/Models/Hris/ClientVisit.php`
        - _Requirements: 1.2, 1.3_

    - [x] 1.3 Write unit tests for ClientVisit model changes
        - Test `TYPE_PLANNED` and `TYPE_SUDDEN` constants exist and have correct values
        - Test `visit_type` is in `$fillable`
        - Test `getVisitTypeLabels()` returns correct labels
        - File: `tests/Unit/Hris/ClientVisitTest.php`
        - _Requirements: 1.2, 1.3_

- [x]   2. Create ClientVisitObserver with visit type classification and attendance update
    - [x] 2.1 Create ClientVisitObserver with `creating` event handler
        - Create file at `app/Observers/Hris/ClientVisitObserver.php`
        - Implement `creating(ClientVisit $visit)`: compare `visit_date` to `today()`, set `visit_type` to `'sudden'` if equal, `'planned'` if future
        - _Requirements: 2.1, 2.2, 2.3_

    - [x] 2.2 Add `created` event handler for attendance log update
        - In `ClientVisitObserver`, implement `created(ClientVisit $visit)`
        - Only act when `visit_type === 'sudden'`
        - Find existing `AttendanceLog` for `employee_id` + today using `forEmployee` and `forDate` scopes
        - If found: update `status`, `source`, `client_visit_id`
        - If not found: create new `AttendanceLog` with `status='visit'`, `source='visit'`, `date=today`, `client_visit_id`
        - Wrap in try/catch for graceful degradation (log error, don't prevent ClientVisit creation)
        - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5_

    - [x] 2.3 Register ClientVisitObserver in AppServiceProvider
        - Add `ClientVisit::observe(ClientVisitObserver::class)` in the `boot()` method
        - Follow the existing `Ticket::observe(TicketObserver::class)` pattern
        - File: `app/Providers/AppServiceProvider.php`
        - _Requirements: 5.1, 5.2_

    - [x]\* 2.4 Write property test: Future visit dates are classified as planned
        - **Property 1: Future visit dates are classified as planned**
        - 100 iterations with random future dates (1–365 days ahead), verify `visit_type` is `'planned'`
        - **Validates: Requirements 2.1**
        - File: `tests/Feature/Hris/ClientVisitObserverPropertyTest.php`

    - [x]\* 2.5 Write property test: Same-day visit updates existing attendance log
        - **Property 3: Same-day visit updates existing attendance log**
        - 100 iterations: create employee with existing AttendanceLog for today, create ClientVisit with today's date, verify AttendanceLog `status='visit'`, `source='visit'`, `client_visit_id` set
        - **Validates: Requirements 4.2, 4.3, 4.4**
        - File: `tests/Feature/Hris/ClientVisitObserverPropertyTest.php`

    - [x]\* 2.6 Write property test: Same-day visit creates attendance log when none exists
        - **Property 4: Same-day visit creates attendance log when none exists**
        - 100 iterations: create employee without AttendanceLog for today, create ClientVisit with today's date, verify new AttendanceLog created with correct fields
        - **Validates: Requirements 4.5**
        - File: `tests/Feature/Hris/ClientVisitObserverPropertyTest.php`

- [x]   3. Checkpoint - Verify observer logic
    - Ensure all tests pass, ask the user if questions arise.

- [x]   4. Create Filament ClientVisitResource
    - [x] 4.1 Create ClientVisitResource with form schema
        - Create `app/Filament/Resources/ClientVisitResource.php`
        - Navigation group: `'HRIS'`
        - Form fields: `employee_id` (Select with employee relationship), `client_name`, `client_address`, `purpose` (Select from `getPurposeLabels()`), `purpose_detail`, `visit_date` (DatePicker with `after_or_equal:today` rule), `planned_start_time`, `planned_end_time`, `notes`, `status` (Select from `getStatusLabels()`)
        - Follow the `AttendanceLogResource` pattern for form structure
        - _Requirements: 3.1, 3.2, 3.5, 6.2_

    - [x] 4.2 Add table schema to ClientVisitResource
        - Table columns: employee name, client name, visit date, visit type (badge with distinct colors for `planned`/`sudden`), purpose, status
        - Use `TextColumn::badge()` for `visit_type` with color mapping
        - _Requirements: 3.3, 3.4_

    - [x] 4.3 Create CRUD pages for ClientVisitResource
        - Create `app/Filament/Resources/ClientVisitResource/Pages/ListClientVisits.php`
        - Create `app/Filament/Resources/ClientVisitResource/Pages/CreateClientVisit.php`
        - Create `app/Filament/Resources/ClientVisitResource/Pages/EditClientVisit.php`
        - Follow the existing page patterns from `AttendanceLogResource/Pages`
        - _Requirements: 3.1_

    - [x]\* 4.4 Write property test: Visit date validation rejects past dates
        - **Property 2: Visit date validation rejects past dates**
        - 100 iterations: random past dates should fail validation, random today/future dates should pass
        - Test both API controller and Filament resource validation consistency
        - **Validates: Requirements 3.2, 3.5, 6.1, 6.2**
        - File: `tests/Feature/Hris/ClientVisitResourceTest.php`

    - [x]\* 4.5 Write feature tests for ClientVisitResource
        - Test form contains all required fields
        - Test table displays required columns including `visit_type` badge
        - Test creating a visit with today's date succeeds
        - File: `tests/Feature/Hris/ClientVisitResourceTest.php`
        - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

- [x]   5. Verify API validation consistency
    - [x] 5.1 Verify ClientVisitController `store` method uses `after_or_equal:today` rule
        - Confirm existing validation in `app/Http/Controllers/Api/Hris/ClientVisitController.php` has no regression
        - Ensure the Filament resource form uses the same `after_or_equal:today` rule
        - _Requirements: 6.1, 6.2_

- [x]   6. Final checkpoint - Ensure all tests pass
    - Ensure all tests pass, ask the user if questions arise.

- [x]   7. Mobile app client visit updates
    - [x] 7.1 Add `visit_type` to `ClientVisit` TypeScript interface
        - Add `visit_type: 'planned' | 'sudden'` to the `ClientVisit` interface in `services/api.ts`
        - _Requirements: 7.1_

    - [x] 7.2 Update visit-request default date to today
        - Change default `visitDate` state from `addDays(new Date(), 1)` to `new Date()`
        - File: `homefinder-app/frontend/app/visit-request.tsx`
        - _Requirements: 7.2_

    - [x] 7.3 Display visit type badge in visit-history
        - Add a visit type badge (Planned/Sudden) to each visit card in `visit-history.tsx`
        - Use red/danger for `sudden`, blue/info for `planned`
        - _Requirements: 7.3, 7.4_

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties using the 100-iteration loop pattern with Faker (matching `ProjectArchivePropertyTest.php` convention)
- Unit tests validate specific examples and edge cases
- The observer follows the existing `TicketObserver` registration pattern in `AppServiceProvider`
