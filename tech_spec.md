## HRIS Technical Specification

**Document Version**: 0.1  
**Last Updated**: 2025-11-21  
**Status**: Draft  

---

## 1. Overview

The HRIS (Human Resources Information System) module extends the existing PartnersHub Project Management system to manage employees, time & attendance, payroll, talent, and employee self-service (ESS), with Indonesian HR and tax context in mind.

The HRIS module will:

- **Reuse** the existing `users` table as the primary identity for employees.
- **Add** HR-specific data models (employee profile, organization structure, leave, attendance, payroll, etc.).
- **Expose** admin UX via Filament 3 resources and pages.
- **Expose** ESS and mobile endpoints via REST/JSON APIs for web and React Native / Expo.
- **Integrate** with the existing project management domain (divisions, projects, todos, notifications).

Non-goal: building full statutory-compliant payroll engine for all edge cases in Indonesia at first iteration. Instead, start with a configurable engine that can be tightened over time.

---

## 2. Scope Breakdown (Mapped to `HRIS_PROMPT.md`)

### 2.1 Core HR (Master Data)

- Employee database (NIK, contact, emergency contact, bank info).
- Document management (contracts, IDs, NPWP, ijazah, SP).
- Organizational structure (department, division, position, manager).
- Movement / career history (promotions, demotions, transfers, salary changes).

### 2.2 Time & Attendance

- Online attendance: web and mobile check-in/check-out.
- Optional GPS coordinate capture.
- Hooks for face recognition / biometric validation (pluggable).
- Leave management:
  - Annual leave balance with 12 days for employees with ≥ 1 year tenure.
  - Annual leave is **not** accumulated to the next year.
  - Maternity leave: 6 weeks before + 6 weeks after delivery.
  - Paternity leave (istri hamil): 3 days.
- Configurable approval workflows (e.g. Staff → Head Division → HR → Finance).

### 2.3 Payroll & Compensation

- Salary components: basic, fixed allowances, non-fixed allowances, deductions.
- PPh 21 calculation modes: Netto, Gross, Gross Up (TER latest).
- BPJS Kesehatan & Ketenagakerjaan calculation (company + employee).
- Reimbursement / claim with plafon balances.
- Payslip generation (PDF or HTML) with optional password protection.

### 2.4 Talent Management

- Recruitment / ATS: applicants, job postings, interview schedule.
- Performance appraisal: KPI/OKR definitions, review cycles, scores.
- Training & development: training records, onboarding materials, certifications.

### 2.5 Employee Self-Service (ESS)

- Employee portal:
  - View leave balance.
  - View/download payslips.
  - Update own profile (address, contact, bank, emergency contact).
  - Submit leave requests.
  - Submit reimbursement requests.
- Mobile app (React Native / Expo) consuming the same APIs for:
  - Attendance / check-in-check-out.
  - Leave, claim, profile, and payslip access.

### 2.6 Analytics & Reporting

- HR dashboard: headcount, turnover, age distribution, leave usage, attendance KPIs.
- Custom exports for audit/management (CSV/XLSX, reusing `maatwebsite/excel`).

---

## 3. High-Level Architecture

### 3.1 Application Layering

- **Backend**: Laravel 12 (existing app).
  - **Domain modules**:
    - `CoreHR` (employees, org structure, documents, movements).
    - `TimeAttendance` (attendance, leave, balances).
    - `Payroll` (salary structure, payroll runs, payslips).
    - `Talent` (recruitment, performance, training).
    - `ESS` (self-service, mobile API).
  - Organized under `app/Models/Hris`, `app/Services/Hris`, `app/Filament/Hris`, and `app/Http/Controllers/Hris`.

- **Admin UI**: Filament 3
  - New Filament navigation groups: `HRIS`, with subsections for Core HR, Time & Attendance, Payroll, Talent.
  - Use Filament Shield for fine-grained permissions (HR admin, HR staff, finance, manager, employee).

- **ESS Web UI**:
  - Option A: Filament Panel dedicated to ESS (simplified UX for employees).
  - Option B: Blade/Livewire pages under `routes/web.php` with dedicated layout.

- **Mobile App**:
  - React Native / Expo project (separate repository).
  - Communicates via secure JSON APIs (JWT or Laravel Sanctum).

### 3.2 Integration with Existing Domain

- `users` table remains the single source of identity.
- `users.division` and `users.is_division_head` are used in:
  - Org tree (mapping user → division → manager).
  - Approval routing (Head Division as default approver).
- Notifications leverage existing `Notification` model and `NotificationService`:
  - Notify approvers on new leave/claim.
  - Notify employees on approvals/rejections and payslip availability.
- Daily/weekly todos can optionally link to performance goals or training tasks (future enhancement).

---

## 4. Data Model

> Note: Field types are conceptual; actual migrations will use Laravel schema builders.

### 4.1 Core HR Tables

#### 4.1.1 `employees`

- Relationship: **1:1** with `users` (or merged into `users` via additional columns).
- Fields (if separate table linked to `users`):
  - `id` (PK)
  - `user_id` (FK → users)
  - `nik` (string, unique, nullable)
  - `date_of_birth` (date, nullable)
  - `place_of_birth` (string, nullable)
  - `gender` (enum: male, female, other)
  - `marital_status` (enum: single, married, divorced, widowed)
  - `address` (text)
  - `phone` (string)
  - `emergency_contact_name` (string)
  - `emergency_contact_phone` (string)
  - `bank_name` (string)
  - `bank_account_number` (string)
  - `bank_account_holder` (string)
  - `npwp_number` (string, nullable)
  - `bpjs_kesehatan_number` (string, nullable)
  - `bpjs_ketenagakerjaan_number` (string, nullable)
  - `hire_date` (date)
  - `employment_status` (enum: permanent, contract, intern, freelance)
  - `employment_end_date` (date, nullable)
  - `is_active` (boolean)

#### 4.1.2 `departments`

- `id`
- `name`
- `code`
- `parent_id` (self-referential for tree)

#### 4.1.3 `positions`

- `id`
- `title`
- `code`
- `level` (string/int; e.g., staff, supervisor, manager, director)

#### 4.1.4 `employee_positions`

- Pivot to track assignment and history:
  - `id`
  - `employee_id` (FK)
  - `department_id` (FK)
  - `position_id` (FK)
  - `manager_id` (FK → employees / users)
  - `start_date`
  - `end_date` (nullable)
  - `is_primary` (boolean)

#### 4.1.5 `employee_movements`

- Records promotions, demotions, transfers, salary changes:
  - `id`
  - `employee_id`
  - `movement_type` (enum: promotion, demotion, transfer, salary_increase, salary_decrease)
  - `from_department_id` (nullable)
  - `to_department_id` (nullable)
  - `from_position_id` (nullable)
  - `to_position_id` (nullable)
  - `effective_date`
  - `notes`
  - `created_by` (FK → users)

#### 4.1.6 `employee_documents`

- `id`
- `employee_id`
- `type` (enum: contract, ktp, npwp, ijazah, sp, other)
- `file_path`
- `issued_date` (nullable)
- `expiry_date` (nullable)
- `notes`

### 4.2 Time & Attendance Tables

#### 4.2.1 `attendance_logs`

- `id`
- `employee_id`
- `date`
- `check_in_time` (datetime, nullable)
- `check_in_latitude` / `check_in_longitude` (nullable)
- `check_out_time` (datetime, nullable)
- `check_out_latitude` / `check_out_longitude` (nullable)
- `source` (enum: web, mobile, biometric, other)
- `status` (enum: present, late, half_day, absent, leave, holiday)

> Face recognition/biometric: store only references to vendor transaction IDs or tokens in `metadata` JSON for privacy.

#### 4.2.2 `leave_types`

- `id`
- `code` (e.g., ANNUAL, MATERNITY, PATERNITY)
- `name`
- `description`
- `requires_approval` (boolean)
- `default_annual_quota` (int, for annual leave)
- `carry_forward_allowed` (boolean, default false for annual leave per spec)
- `gender_constraint` (nullable enum: male, female) for maternity/paternity rules.
- `is_special` (boolean) for maternity/paternity with rule-based duration.

#### 4.2.3 `leave_balances`

- `id`
- `employee_id`
- `leave_type_id`
- `year`
- `starting_balance`
- `used_days`
- `remaining_days`

#### 4.2.4 `leave_requests`

- `id`
- `employee_id`
- `leave_type_id`
- `start_date`
- `end_date`
- `total_days`
- `reason` (text)
- `status` (enum: draft, pending, approved, rejected, cancelled)
- `current_approver_id` (FK → users)
- `workflow_definition_id` (FK → approval workflow template)
- `metadata` (json)

#### 4.2.5 `approval_workflows`

- Define approval templates such as Staff → Head Division → HR → Finance:
  - `id`
  - `name`
  - `for_module` (enum: leave, reimbursement, payroll_adjustment, generic)
  - `steps` (JSON array of step definitions: role-based or user-based)

#### 4.2.6 `approval_steps`

- Materialized steps per request:
  - `id`
  - `workflow_id`
  - `approvable_type` (morph to `LeaveRequest`, `Reimbursement`, etc.)
  - `approvable_id`
  - `step_order`
- `approver_id` (FK → users, nullable if role-based)
- `approver_role` (string/enum; e.g., head_division, hr, finance)
- `status` (pending, approved, rejected, skipped)
- `approved_at`
- `notes`

### 4.3 Payroll & Compensation Tables

#### 4.3.1 `salary_structures`

- `id`
- `employee_id`
- `effective_date`
- `basic_salary`
- `allowance_fixed` (JSON or structured table)
- `allowance_variable` (JSON or structured table)
- `deductions` (JSON)
- `pph21_method` (enum: netto, gross, gross_up)
- `bpjs_kesehatan_rate_employee`
- `bpjs_kesehatan_rate_company`
- `bpjs_ketenagakerjaan_rate_employee`
- `bpjs_ketenagakerjaan_rate_company`

#### 4.3.2 `payrun_batches`

- `id`
- `period_start_date`
- `period_end_date`
- `pay_date`
- `status` (draft, calculated, approved, paid)
- `notes`

#### 4.3.3 `payroll_records`

- `id`
- `payrun_batch_id`
- `employee_id`
- `gross_income`
- `taxable_income`
- `pph21_amount`
- `bpjs_kesehatan_employee`
- `bpjs_kesehatan_company`
- `bpjs_ketenagakerjaan_employee`
- `bpjs_ketenagakerjaan_company`
- `total_deductions`
- `net_income`
- `details` (JSON breakdown)

#### 4.3.4 `reimbursements`

- `id`
- `employee_id`
- `type` (enum: medical, glasses, transport, other)
- `amount`
- `description`
- `receipt_file_path`
- `status` (pending, approved, rejected, paid)
- `workflow_definition_id`
- `metadata` (json)

### 4.4 Talent Management Tables

#### 4.4.1 `job_postings`

- `id`
- `title`
- `department_id`
- `location`
- `employment_type`
- `description`
- `status` (draft, published, closed)

#### 4.4.2 `applicants`

- `id`
- `job_posting_id`
- `name`
- `email`
- `phone`
- `resume_file_path`
- `status` (applied, shortlisted, interviewed, offered, hired, rejected)

#### 4.4.3 `interviews`

- `id`
- `applicant_id`
- `interviewer_id` (FK → users)
- `scheduled_at`
- `result` (enum: pending, pass, fail)
- `notes`

#### 4.4.4 `performance_cycles`

- `id`
- `name`
- `period_start_date`
- `period_end_date`
- `status` (draft, active, closed)

#### 4.4.5 `performance_goals`

- `id`
- `employee_id`
- `cycle_id`
- `title`
- `description`
- `weight`
- `target_value`
- `actual_value`
- `score` (numeric)

#### 4.4.6 `performance_reviews`

- `id`
- `employee_id`
- `cycle_id`
- `reviewer_id`
- `overall_score`
- `comments`
- `created_at`

### 4.5 ESS and Analytics Tables

Analytics will largely be served via aggregated queries and possibly materialized summary tables (e.g., `hr_kpi_summaries` for dashboards) if needed for performance.

---

## 5. Key Domain Logic

### 5.1 Leave Rules (Indonesia-Specific)

- **Annual Leave**:
  - Eligibility: employees with tenure ≥ 1 year (based on `hire_date`).
  - Quota: 12 days per calendar year.
  - Carry forward: not allowed; balances reset annually.
  - Balance calculation:
    - On year start: `remaining_days = 12`.
    - On approved leave: decrement `remaining_days`.
    - Prevent approval if `remaining_days < requested_days`.

- **Maternity Leave**:
  - Leave type: `MATERNITY`.
  - Duration: 6 weeks (42 days) **before** and 6 weeks (42 days) **after** estimated delivery date.
  - Implementation:
    - Allow creation of two linked `LeaveRequest` records or store both ranges in metadata.
    - Only for employees of gender `female`.

- **Paternity Leave (Cuti Istri Hamil)**:
  - Leave type: `PATERNITY`.
  - Duration: 3 days.
  - Only for employees of gender `male`.

### 5.2 Approval Workflow Engine

- Configurable workflows defined per module & type using `approval_workflows`.
- Runtime instantiation:
  1. When `LeaveRequest`/`Reimbursement` is created, pick workflow based on:
     - Employee’s department/division.
     - Leave/claim type configuration.
  2. Generate `approval_steps` with:
     - Step order, role/user mapping (e.g., head_division, hr, finance).
  3. Mark first step as `pending` and set `current_approver_id`.
  4. On approval:
     - Mark current step as `approved`.
     - Move to next step or close as `approved` when last step done.
  5. On rejection:
     - Mark request as `rejected`.
     - Record rejection reason.

### 5.3 Payroll Calculation Flow

1. HR/Finance initiates `payrun_batch` for period.
2. System fetches:
   - Active employees (`employees.is_active`).
   - Salary structures.
   - Attendance, leave usage, overtime (future).
3. For each employee:
   - Compute gross income (basic + allowances).
   - Compute BPJS contributions based on configured rates.
   - Compute taxable income and PPh21 according to selected method.
   - Store breakdown in `payroll_records.details`.
4. HR validates and locks batch (`status = approved`).
5. Finance marks as `paid` and optionally exports bank transfer file.
6. Payslips:
   - Generated from `payroll_records` (HTML → PDF).
   - Protected by password (e.g., last 6 digits of NIK or date of birth).

---

## 6. API Design (ESS & Mobile)

Base URL: `/api/hris/...` (guarded via Sanctum/JWT).

### 6.1 Authentication & Profile

- `GET /api/hris/profile`  
  - Return employee profile + HR fields + current position.

- `PUT /api/hris/profile`  
  - Update allowed fields (address, contact, bank, emergency contact).

### 6.2 Attendance

- `POST /api/hris/attendance/check-in`
  - Body: `{ "latitude": float, "longitude": float, "source": "mobile|web", "photo_token": "optional" }`.

- `POST /api/hris/attendance/check-out`
  - Body similar to check-in.

- `GET /api/hris/attendance/daily?date=YYYY-MM-DD`
- `GET /api/hris/attendance/history?from=...&to=...`

### 6.3 Leave

- `GET /api/hris/leave/balances?year=YYYY`
- `GET /api/hris/leave/types`
- `GET /api/hris/leave/requests`
- `POST /api/hris/leave/requests`
  - Body: `{ "leave_type_id", "start_date", "end_date", "reason" }`.
- `PUT /api/hris/leave/requests/{id}/cancel`

### 6.4 Reimbursements

- `GET /api/hris/reimbursements`
- `POST /api/hris/reimbursements`
  - Supports file upload for receipts.

### 6.5 Payslips

- `GET /api/hris/payslips`
- `GET /api/hris/payslips/{id}`  
  - Returns metadata + secure download URL.

All endpoints will:
- Use authenticated user’s `employee_id` to scope data.
- Reuse existing notification system for push/alerts (if extended to mobile).

---

## 7. Filament Admin Resources

### 7.1 Core HR Resources

- `EmployeeResource`
- `DepartmentResource`
- `PositionResource`
- `EmployeePositionResource` (relation managers).
- `EmployeeMovementResource`
- `EmployeeDocumentResource`

### 7.2 Time & Attendance Resources

- `AttendanceLogResource` (primarily for HR audit).
- `LeaveTypeResource`
- `LeaveRequestResource`
- `ApprovalWorkflowResource`

### 7.3 Payroll Resources

- `SalaryStructureResource`
- `PayrunBatchResource` (with actions: calculate, approve, mark as paid, export).
- `PayrollRecordResource` (read-only detail inside payrun).
- `ReimbursementResource`

### 7.4 Talent Resources

- `JobPostingResource`
- `ApplicantResource`
- `InterviewResource`
- `PerformanceCycleResource`
- `PerformanceGoalResource`
- `PerformanceReviewResource`

Each resource:
- Uses Filament Shield permissions (e.g., `view_any_employee`, `update_payroll_record`).
- Is grouped under `HRIS` navigation group in `AdminPanelProvider`.

---

## 8. Permissions & Roles

Using existing Spatie Permission / Filament Shield setup, introduce HR-related roles:

- `hr-admin`
  - Full HRIS access, including payroll configuration and payruns.
- `hr-staff`
  - Core HR + attendance + leave handling; limited payroll view (no salary configuration).
- `finance`
  - Access to payroll batches, reimbursements, and financial exports.
- `manager`
  - Approve leave and reimbursement for subordinates; view team attendance.
- `employee`
  - ESS access only (self profile, own attendance, own leave, own payslips).

Permissions are granted via:
- Role → permission mapping defined in Filament Shield.
- Approval workflows use roles (`head_division`, `hr-admin`, `finance`) to derive approvers.

---

## 9. Security, Compliance, and Auditing

- Sensitive data (salary, bank info, NPWP, BPJS numbers):
  - Restrict access via policies and permissions.
  - Mask in logs and UI where appropriate.
- Documents:
  - Store in `storage/app/hris/...` with private visibility.
  - Access via signed URLs with short expiration.
- Audit logging:
  - Track changes to critical data (salary, position, movements, approvals) using model observers or dedicated `audit_logs` table.
- PII and regulatory compliance (Indonesia):
  - Allow export of employee data for regulatory audits.
  - Provide account deactivation and data retention policies (to be defined with HR/legal).

---

## 10. Non-Functional Requirements

- **Performance**:
  - Pagination and indexing on all large tables (`employee_id`, `date`, `period`, `status`).
  - Optional summary tables for heavy analytics.
- **Scalability**:
  - Compatible with existing Octane/FrankenPHP setup.
  - Long-running batch jobs (payroll calculation) use Laravel queues.
- **Reliability**:
  - Idempotent payrun recalculation (draft vs locked).
  - Email / notification failures do not break core workflows.
- **Extensibility**:
  - Workflows defined via JSON allow new routes without code change.
  - Payroll calculation encapsulated in service classes for future tax logic updates.

---

## 11. Implementation Roadmap (High Level)

1. **Phase 1 – Core HR + Org Structure**
   - `employees`, `departments`, `positions`, `employee_positions`, `employee_documents`, `employee_movements`.
   - Filament resources and basic ESS profile endpoints.
2. **Phase 2 – Time & Attendance + Leave**
   - `attendance_logs`, `leave_types`, `leave_balances`, `leave_requests`, workflow engine.
   - Web and mobile APIs for attendance and leave; manager/HR approval workflows.
3. **Phase 3 – Payroll & Payslips**
   - `salary_structures`, `payrun_batches`, `payroll_records`, `reimbursements`.
   - Calculation engine, exports, payslip generation, ESS access.
4. **Phase 4 – Talent & Analytics**
   - Recruitment, performance, training.
   - HR dashboards and exportable analytics.

Each phase should include:
- Database migrations.
- Models & relationships.
- Filament resources & policies.
- API endpoints & tests.
- Documentation updates.

---

## 12. Mobile App (Expo) Implementation Plan

### 12.1 Technology Stack

- **Framework**: React Native with **Expo (Managed Workflow)**.
- **Language**: TypeScript.
- **Navigation**: `expo-router` or `@react-navigation/native` (stack + bottom tabs).
- **Network**: `axios` or `fetch` wrapped in a small API client layer.
- **State / Data**: `@tanstack/react-query` for server state, plus minimal `zustand` or context for auth/session.
- **Auth Storage**: `expo-secure-store` for access/refresh tokens (avoid plain AsyncStorage for tokens).
- **Location & Camera**:
  - `expo-location` for GPS coordinates during attendance.
  - `expo-camera` for optional selfie/photo capture (for future face recognition).
- **Push Notifications**: Expo Notifications (Expo Push Token registered to Laravel backend).

Environments:
- `dev`, `staging`, `prod` with separate API base URLs (e.g., using `app.config.ts` and env vars).

### 12.2 High-Level App Architecture

- **App Shell**:
  - `AuthStack` (unauthenticated): Login, optional Forgot Password, first-time permissions prompts.
  - `MainTabs` (authenticated):
    - `Home` (dashboard + quick actions).
    - `Attendance`.
    - `Leave`.
    - `Payroll`.
    - `Profile`.

- **API Client Layer**:
  - Central `ApiClient` wrapper handling:
    - Base URL, auth headers (Bearer token).
    - Error handling (401 → logout/refresh).
    - Network timeouts and retries (where appropriate).

- **Auth Flow**:
  - Credentials-based login (e.g., email/password) calling a Laravel endpoint such as `POST /api/auth/login` (to be defined).
  - Response returns `access_token` (+ optional `refresh_token`).
  - Tokens stored in `expo-secure-store`.
  - On app start:
    - Load tokens from secure store.
    - Validate token (e.g., `GET /api/hris/profile`).
    - If invalid/expired, redirect to login.

> If you later add SSO/Google login for mobile, this can be integrated via Expo AuthSession or native SDKs, still converting to backend-issued tokens.

### 12.3 Screens & UX (MVP Scope)

#### 12.3.1 Auth

- **Login Screen**:
  - Email + Password inputs.
  - “Remember me” concept via persisted token in secure storage.
  - Error handling for invalid credentials / network failure.

#### 12.3.2 Home

- Key widgets:
  - Today’s attendance status (Checked-in, Not checked-in, Checked-out).
  - Leave balance summary (annual leave remaining).
  - Next upcoming leave or holiday.
  - Quick actions: “Check In”, “Request Leave”, “View Payslip”.

#### 12.3.3 Attendance

- **Today Screen**:
  - Shows:
    - Current date.
    - Check-in status/time.
    - Check-out status/time.
    - Location info (city/approx, if available).
  - Primary actions:
    - `Check In` button:
      - Requests location permission via `expo-location`.
      - Optionally opens camera for selfie (configurable).
      - Calls `POST /api/hris/attendance/check-in` with:
        - `latitude`, `longitude`, `source: "mobile"`, optional `photo_token`.
    - `Check Out` button:
      - Same pattern using `POST /api/hris/attendance/check-out`.

- **History Screen**:
  - List of past days with:
    - Status (present, late, leave, absent).
    - Check-in / check-out times.
  - Uses `GET /api/hris/attendance/history?from=...&to=...`.

- **Offline Considerations (Phase 2+)**:
  - If offline, queue attendance events locally with timestamp + GPS snapshot.
  - Sync queued events when back online.

#### 12.3.4 Leave

- **Leave Balance Screen**:
  - Uses `GET /api/hris/leave/balances?year=YYYY`.
  - Shows breakdown: annual, maternity/paternity (if applicable), other leave types.

- **Leave Requests List**:
  - Uses `GET /api/hris/leave/requests`.
  - Shows status chips (Pending, Approved, Rejected, Cancelled).

- **Create Leave Request Screen**:
  - Fetch leave types via `GET /api/hris/leave/types`.
  - Form fields:
    - Leave type (select).
    - Start date, end date (date pickers).
    - Reason (textarea).
  - On submit:
    - `POST /api/hris/leave/requests`.
  - Validation:
    - Prevent start/end in the past (configurable).
    - Show backend error if balance insufficient.

#### 12.3.5 Reimbursements (Phase 2+)

- **Reimbursement List** (`GET /api/hris/reimbursements`).
- **New Reimbursement Screen**:
  - Type, amount, description, attach receipt (using `ImagePicker` or `DocumentPicker`).
  - `POST /api/hris/reimbursements` with multipart upload for receipts.

#### 12.3.6 Payroll / Payslips

- **Payslip List Screen**:
  - Uses `GET /api/hris/payslips`.
  - Shows monthly entries with status (available, not generated).

- **Payslip Detail Screen**:
  - Uses `GET /api/hris/payslips/{id}`.
  - Option A: show breakdown inline (gross/net/BPJS/PPH21).
  - Option B: open PDF in viewer using returned secure URL.

#### 12.3.7 Profile

- **Profile Screen**:
  - Displays:
    - Name, email, division, position.
    - Contact info (phone, address).
    - Bank info (if permission allows).
  - Actions:
    - “Edit Profile” → `PUT /api/hris/profile` with allowed fields only.
    - “Logout” → clear secure storage, reset query cache, go to login.

### 12.4 Notifications & Approvals

- **Push Notification Flow**:
  - On app start, register for push notifications with Expo.
  - Obtain Expo push token and send to backend via dedicated endpoint:  
    `POST /api/hris/devices` `{ "expo_push_token": "..." }`.
  - Backend maps token to authenticated user/employee.
  - Use Expo Push API from Laravel backend:
    - New leave approval task for manager.
    - Leave/claim approval/rejection notice.
    - Payslip available.

- **In-App Handling**:
  - When user taps notification:
    - Navigate to relevant screen (leave detail, reimbursement detail, payslip, etc.).

> Manager/approver flows (approve/reject from mobile) can be added in a later iteration by exposing additional endpoints like `POST /api/hris/leave/requests/{id}/approve`.

### 12.5 Security & Compliance (Mobile)

- Use **HTTPS only** for all API calls.
- Store tokens only in `expo-secure-store`.
- Avoid logging sensitive payloads (tokens, salary info) to console or analytics.
- Implement inactivity timeout / optional PIN/biometric lock (Phase 2+):
  - Use OS biometrics (Face ID / Touch ID) via Expo modules for re-auth inside app.

### 12.6 Delivery & Release Strategy

- **Phase 1 (MVP Mobile)**:
  - Implement Auth, Home, Attendance (check-in/out), Leave Balances, Leave Requests, Profile (read-only/basic edit).
  - Internal beta distribution via Expo (no app store yet).

- **Phase 2**:
  - Reimbursements, Payslips, offline attendance queue, basic push notifications.
  - Prepare store assets (icons, screenshots, description) and release to TestFlight / internal testing on Play Store.

- **Phase 3**:
  - Manager approval actions, performance/HR announcements, optional biometric lock, more advanced dashboards.

---

## 13. Phase 1 Implementation Notes

- **Database**: migrations for employees, departments, positions, documents, movements, leave types, balances, and requests. Published Sanctum migrations to support API tokens.
- **Models & Services**:
  - New HRIS model namespace (`App\Models\Hris\*`) with relationships and helper scopes.
  - `LeaveBalanceService` and `LeaveWorkflowService` encapsulate entitlement logic and approval routing.
- **Policies**: `EmployeePolicy` and `LeaveRequestPolicy` secure Filament resources and API usage; registered via the new `AuthServiceProvider`.
- **Filament Resources**: Employees (with relation managers), Departments, Positions, Leave Types, and Leave Requests, grouped under the `HRIS` navigation section.
- **API**: Sanctum-protected routes under `/api/hris` for profile management, leave balances/types, and leave requests (create/list/cancel).
- **Seeders**: `HrisSeeder` populates baseline departments, positions, and the required leave types (Annual, Maternity, Paternity).
- **Testing**: Pest coverage for leave balance calculations plus API endpoints to guard regressions.
- **Docs**: README updated with HRIS instructions, including migrations, seeding, and token issuance.
