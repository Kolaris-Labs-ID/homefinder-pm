# Requirements Document

## Introduction

This feature enables employees to record sudden (same-day) client visits through the system. Currently, the API controller already accepts `after_or_equal:today` for `visit_date`, but there is no Filament admin resource for ClientVisit, no `visit_type` classification (Planned vs Sudden), and no automatic attendance status update when a same-day visit is created. This feature adds visit type classification, a Filament ClientVisitResource, and an observer that updates the employee's attendance record to 'visit' status when a same-day visit is created.

## Glossary

- **ClientVisit**: The Eloquent model (`App\Models\Hris\ClientVisit`) representing a scheduled or sudden client visit by an employee.
- **AttendanceLog**: The Eloquent model (`App\Models\Hris\AttendanceLog`) representing an employee's daily attendance record, including status and check-in/check-out data.
- **VisitResource**: The Filament admin resource for managing ClientVisit records (to be created as `ClientVisitResource`).
- **ClientVisitObserver**: A Laravel model observer registered on the ClientVisit model that reacts to lifecycle events.
- **visit_type**: A new string column on the `client_visits` table that classifies a visit as either `planned` or `sudden`.
- **Employee**: The Eloquent model (`App\Models\Hris\Employee`) representing a company employee.

## Requirements

### Requirement 1: Add visit_type Column to ClientVisit

**User Story:** As a system administrator, I want each client visit to be automatically classified as 'Planned' or 'Sudden', so that I can distinguish between pre-scheduled and same-day visits in reports and listings.

#### Acceptance Criteria

1. THE Migration SHALL add a `visit_type` string column to the `client_visits` table with allowed values `planned` and `sudden`, defaulting to `planned`.
2. THE ClientVisit model SHALL include `visit_type` in its `$fillable` array.
3. THE ClientVisit model SHALL define constants `TYPE_PLANNED = 'planned'` and `TYPE_SUDDEN = 'sudden'`.

### Requirement 2: Automatic Visit Type Classification

**User Story:** As an employee, I want the system to automatically determine whether my visit is planned or sudden based on the visit date, so that I do not need to manually classify it.

#### Acceptance Criteria

1. WHEN a ClientVisit is being created with a `visit_date` greater than today, THE ClientVisitObserver SHALL set the `visit_type` attribute to `planned`.
2. WHEN a ClientVisit is being created with a `visit_date` equal to today, THE ClientVisitObserver SHALL set the `visit_type` attribute to `sudden`.
3. THE ClientVisitObserver SHALL set the `visit_type` during the `creating` event, before the record is persisted to the database.

### Requirement 3: Create Filament ClientVisitResource

**User Story:** As an administrator, I want a Filament resource for managing client visits, so that I can create, view, edit, and list visits from the admin panel.

#### Acceptance Criteria

1. THE VisitResource SHALL provide a form with fields for `employee_id`, `client_name`, `client_address`, `purpose`, `purpose_detail`, `visit_date`, `planned_start_time`, `planned_end_time`, `notes`, and `status`.
2. THE VisitResource form SHALL validate the `visit_date` field using the `after_or_equal:today` rule, allowing same-day visit creation.
3. THE VisitResource SHALL display a table listing visits with columns for employee name, client name, visit date, visit type, purpose, and status.
4. THE VisitResource SHALL display the `visit_type` column as a badge with distinct colors for `planned` and `sudden`.
5. WHEN a visit is created through the VisitResource form, THE VisitResource SHALL allow the `visit_date` to be set to today's date.

### Requirement 4: Attendance Status Update on Same-Day Visit Creation

**User Story:** As an employee, I want my attendance record to automatically reflect that I am visiting a client when a same-day visit is created, so that my attendance status is accurate without manual updates.

#### Acceptance Criteria

1. WHEN a ClientVisit is created with a `visit_date` equal to today, THE ClientVisitObserver SHALL find the employee's AttendanceLog record for today.
2. WHEN a matching AttendanceLog record exists for today, THE ClientVisitObserver SHALL update the AttendanceLog `status` column to `visit`.
3. WHEN a matching AttendanceLog record exists for today, THE ClientVisitObserver SHALL update the AttendanceLog `source` column to `visit`.
4. WHEN a matching AttendanceLog record exists for today, THE ClientVisitObserver SHALL set the AttendanceLog `client_visit_id` to the newly created ClientVisit's ID.
5. IF no AttendanceLog record exists for the employee for today, THEN THE ClientVisitObserver SHALL create a new AttendanceLog record with `status` set to `visit`, `source` set to `visit`, `date` set to today, and `client_visit_id` set to the new ClientVisit's ID.

### Requirement 5: Observer Registration

**User Story:** As a developer, I want the ClientVisitObserver to be properly registered in the application, so that it fires on ClientVisit model events.

#### Acceptance Criteria

1. THE AppServiceProvider SHALL register the ClientVisitObserver on the ClientVisit model in its `boot` method.
2. THE ClientVisitObserver SHALL be located at `app/Observers/Hris/ClientVisitObserver.php` following the existing observer namespace convention.

### Requirement 7: Mobile App Client Visit Updates

**User Story:** As an employee using the mobile app, I want to be able to schedule a same-day (sudden) client visit and see whether each visit is Planned or Sudden, so that I have the same capabilities as the admin panel.

#### Acceptance Criteria

1. THE `ClientVisit` TypeScript interface in `services/api.ts` SHALL include a `visit_type` field with type `'planned' | 'sudden'`.
2. THE visit request screen SHALL default the visit date to today (not tomorrow), enabling sudden visit creation by default.
3. THE visit history screen SHALL display a visit type badge (Planned/Sudden) on each visit card with distinct styling.
4. THE visit type badge SHALL use a danger/red style for `sudden` and an info/blue style for `planned`.

### Requirement 6: API Validation Consistency

**User Story:** As a developer, I want the API and Filament resource to use consistent validation for `visit_date`, so that both entry points allow same-day visits.

#### Acceptance Criteria

1. THE ClientVisitController `store` method SHALL validate `visit_date` with the `after_or_equal:today` rule (already in place, verify no regression).
2. THE VisitResource form SHALL validate `visit_date` with the `after_or_equal:today` rule, matching the API validation.
