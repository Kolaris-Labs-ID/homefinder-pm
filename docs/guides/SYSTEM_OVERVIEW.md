# Homefinder PM - Complete System Overview

**Homefinder PM** is a comprehensive Laravel 12-based enterprise application built on Filament 3 that combines **Project Management** and **HRIS (Human Resource Information System)** capabilities.

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12 (PHP 8.2+) |
| Admin Panel | Filament 3.2 |
| Database | MySQL 8.0+ / PostgreSQL 12+ |
| API Auth | Laravel Sanctum |
| OAuth | Laravel Socialite (Google) |
| Permissions | Filament Shield + Spatie Permission |
| Queue | Database-backed with Supervisor |
| Server | FrankenPHP (via Laravel Octane) |
| Testing | Pest PHP 3.7 |

---

## System Modules

### 1. Project Management Module

- **Projects** with customizable ticket prefixes (e.g., WEB-001)
- **Kanban Board** with drag-and-drop ticket management
- **Tickets** with priorities, due dates, multi-user assignment
- **Epics** for grouping related work
- **Timeline/Gantt** visualization
- **Weekly Todos** with division-based organization
- **Comments** and **History** tracking
- **Leaderboard** for team performance
- **External Client Portal** for project visibility

### 2. HRIS Module (4 Phases)

#### Phase 1: Core HR & Org Structure

| Feature | Description |
|---------|-------------|
| **Employee Management** | Personal data, documents, NIK, NPWP, BPJS numbers |
| **Department Management** | Organizational hierarchy |
| **Position Management** | Job titles and assignments |
| **Employee Positions** | Position history with primary designation |
| **Employee Documents** | Document storage (contracts, ID copies) |
| **Employee Movements** | Promotions, transfers, demotions |

#### Phase 2: Time & Attendance

| Feature | Description |
|---------|-------------|
| **Attendance Tracking** | Check-in/out via web & mobile |
| **GPS Location Capture** | Latitude/longitude with reverse geocoding |
| **Photo Capture** | Timestamped photos with address overlay |
| **Work Mode** | Office vs WFH with weekly quota (1 day/week) |
| **Break Management** | Start/end break tracking |
| **Attendance Status** | present, late, half_day, absent, leave, holiday |
| **Payroll Period Linking** | Auto-assign attendance to pay periods |

#### Phase 3: Payroll & Payslips

| Feature | Description |
|---------|-------------|
| **Salary Structures** | Basic salary + allowances with 75:25 rule |
| **BPJS Calculations** | Kesehatan (1%/4%), JHT (2%/3.7%), JP (1%/2%), JKK, JKM |
| **PPh 21 Calculation** | TER method with netto/gross/gross_up options |
| **Payrun Batches** | Draft → Calculated → Approved → Paid workflow |
| **Payroll Records** | Individual employee pay calculations |
| **Reimbursements** | Medical, glasses, transport with approval workflow |
| **Payslips** | ESS access with PDF download (password-protected) |

#### Phase 4: Talent Management & Analytics

| Feature | Description |
|---------|-------------|
| **Job Postings** | Create and publish vacancies |
| **Applicant Tracking** | ATS pipeline: applied → shortlisted → interviewed → offered → hired |
| **Interviews** | Schedule with interviewers, record results |
| **Performance Cycles** | Annual/quarterly evaluation periods |
| **Performance Goals** | KPI/OKR with weighted scoring |
| **Performance Reviews** | Manager evaluations with aggregated scores |
| **Training Records** | Track certifications with expiry notifications |
| **HR Analytics** | Headcount, turnover rate, age/tenure distribution |

#### Leave Management

| Feature | Description |
|---------|-------------|
| **Leave Types** | Annual, Sick, Maternity, Paternity (Indonesian labor law) |
| **Leave Balances** | Yearly quota tracking with rollover rules |
| **Leave Requests** | Submit with attachments, approval workflow |
| **Client Visits** | Schedule visits with check-in/out tracking |

#### Approval Workflows

| Feature | Description |
|---------|-------------|
| **Configurable Steps** | Role-based or user-based approvers |
| **Multi-step Routing** | Sequential approval chain |
| **Approvable Items** | Leave requests, reimbursements |

#### Announcements

- **Types**: news, update, blog, event, birthday
- **Display**: Mobile app home screen, max 5, color-coded icons

---

## Admin Panel Structure (Filament Navigation Groups)

```
📁 HRIS (Phase 1 & 2)
├── Departments
├── Employees
├── Positions
├── Leave Types
├── Leave Requests
├── Attendance Logs
└── Approval Workflows

📁 Payroll (Phase 3)
├── Salary Structures
├── Payrun Batches
└── Reimbursements

📁 Recruitment (Phase 4)
├── Job Postings
├── Applicants
└── Interviews

📁 Performance (Phase 4)
├── Performance Cycles
├── Performance Goals
└── Performance Reviews

📁 Training (Phase 4)
└── Training Records

📁 Project Management
├── Projects
├── Tickets
├── Epics
├── Weekly Todos
└── [Kanban Board, Timeline pages]
```

---

## API Endpoints (Mobile App - ESS)

| Prefix | Endpoint | Purpose |
|--------|----------|---------|
| `/api/auth` | login, google, logout, change-password | Authentication |
| `/api/hris/profile` | GET, PUT | Employee profile |
| `/api/hris/attendance` | check-in, check-out, break/*, daily, history, wfh-quota | Time tracking |
| `/api/hris/leave-*` | balances, types, requests | Leave management |
| `/api/hris/payslips` | list, show, download | Payslip access |
| `/api/hris/reimbursements` | CRUD | Expense claims |
| `/api/hris/visits` | CRUD + check-in/out | Client visits |
| `/api/hris/team` | GET | Team member status |
| `/api/hris/announcements` | GET | Company announcements |
| `/api/hris/notifications` | register-token, settings, list, read | Push notifications |

---

## HRIS Services Architecture

```
app/Services/Hris/
├── AttendanceService.php       # Check-in/out logic
├── ApprovalWorkflowService.php # Approval routing
├── BpjsCalculationService.php  # Indonesian social security
├── GeocodingService.php        # Reverse geocoding
├── HRAnalyticsService.php      # Dashboard metrics
├── LeaveBalanceService.php     # Leave quota management
├── LeaveWorkflowService.php    # Leave approval
├── PayrollCalculationService.php # Salary computation
├── PayrollPeriodService.php    # Pay period logic
├── PayslipService.php          # Payslip generation
├── PerformanceService.php      # KPI/OKR calculations
├── PhotoService.php            # Photo processing
├── Pph21CalculationService.php # Indonesian income tax
├── PushNotificationService.php # FCM notifications
├── RecruitmentService.php      # ATS pipeline
└── ReimbursementService.php    # Expense processing
```

---

## Indonesian HR Context

The system is specifically built for Indonesian companies with:

### BPJS (Social Security)

- **BPJS Kesehatan** (Health Insurance): 
  - Employee: 1% 
  - Company: 4%
  - Wage ceiling: IDR 12,000,000

- **BPJS Ketenagakerjaan** (Workforce Insurance):
  - **JHT** (Old Age Security): 
    - Employee: 2%
    - Company: 3.7%
    - No wage ceiling
  - **JP** (Pension): 
    - Employee: 1%
    - Company: 2%
    - Wage ceiling: IDR 10,547,400
  - **JKK** (Work Accident): 
    - Company only: 0.24%-1.74% (based on risk level)
  - **JKM** (Death Insurance): 
    - Company only: 0.30%

### Tax & Payroll Rules

- **PPh 21** (Income Tax): TER (Tarif Efektif Rata-rata) method calculation
- **75:25 Rule**: Basic salary must be ≥75% of (basic + fixed allowances)
- **Indonesian Leave Types**: Compliant with local labor law

---

## Role-Based Access Control

| Role | Access Level |
|------|--------------|
| Super Admin | Full system access |
| Admin, CEO, COO, CTO, HR | Management roles with broad access |
| Head Division | Division-level access (team todos, team attendance) |
| Finance | Payroll approval and payment |
| HR Admin | Employee management, leave approval |
| Member | Self-service (own data only) |

---

## Key Models

### HRIS Models (`app/Models/Hris/`)

- `Employee` - Employee master data
- `Department` - Organizational units
- `Position` - Job positions
- `EmployeePosition` - Position assignments
- `AttendanceLog` - Daily attendance records
- `LeaveType` - Leave type definitions
- `LeaveBalance` - Employee leave quotas
- `LeaveRequest` - Leave applications
- `SalaryStructure` - Employee compensation
- `PayrunBatch` - Payroll processing batches
- `PayrollRecord` - Individual payroll calculations
- `Reimbursement` - Expense claims
- `ClientVisit` - Client visit tracking
- `ApprovalWorkflow` - Approval workflow templates
- `ApprovalStep` - Workflow step instances
- `Announcement` - Company announcements
- `JobPosting` - Job vacancies
- `Applicant` - Job applicants
- `Interview` - Interview scheduling
- `PerformanceCycle` - Performance evaluation periods
- `PerformanceGoal` - KPI/OKR goals
- `PerformanceReview` - Performance evaluations
- `TrainingRecord` - Training and certifications
- `UserNotification` - Push notifications
- `NotificationSetting` - User notification preferences
- `PushToken` - Mobile device tokens

---

## Database Structure

### Key Tables

- `employees` - Employee master data
- `departments` - Department hierarchy
- `positions` - Job positions
- `employee_positions` - Position assignments
- `attendance_logs` - Daily attendance
- `leave_types` - Leave type definitions
- `leave_balances` - Employee leave quotas
- `leave_requests` - Leave applications
- `salary_structures` - Compensation structures
- `payrun_batches` - Payroll batches
- `payroll_records` - Payroll calculations
- `reimbursements` - Expense claims
- `client_visits` - Visit tracking
- `approval_workflows` - Workflow templates
- `approval_steps` - Workflow steps
- `hris_announcements` - Announcements
- `job_postings` - Job vacancies
- `applicants` - Job applicants
- `interviews` - Interview records
- `performance_cycles` - Evaluation periods
- `performance_goals` - KPI/OKR goals
- `performance_reviews` - Evaluations
- `training_records` - Training data

---

## Mobile App Integration

This backend powers the **Homefinder** mobile app (React Native/Expo) providing:

- Employee self-service (ESS) functionality
- Attendance check-in/out with GPS and photos
- Leave request submission
- Payslip viewing and download
- Reimbursement claims
- Client visit tracking
- Team status viewing
- Push notifications
- Announcement display

---

## Development Commands

### Setup
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan make:filament-user
php artisan shield:setup
php artisan shield:install
php artisan shield:super-admin
```

### Development
```bash
composer dev          # Start server + queue + vite
composer octane-dev  # Start with Octane (FrankenPHP)
php artisan queue:work
npm run dev
```

### Testing
```bash
php artisan test
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit
```

---

## File Structure

```
homefinder-pm/
├── app/
│   ├── Console/Commands/     # Artisan commands
│   ├── Filament/             # Admin panel resources
│   │   ├── Resources/        # CRUD resources
│   │   ├── Pages/            # Custom pages
│   │   └── Widgets/          # Dashboard widgets
│   ├── Http/Controllers/     # API controllers
│   │   └── Api/Hris/         # HRIS API endpoints
│   ├── Models/               # Eloquent models
│   │   └── Hris/             # HRIS models
│   ├── Policies/             # Authorization policies
│   │   └── Hris/             # HRIS policies
│   └── Services/Hris/        # Business logic services
├── database/
│   ├── migrations/           # Database migrations
│   ├── factories/            # Model factories
│   └── seeders/              # Database seeders
├── routes/
│   ├── api.php               # API routes
│   └── web.php               # Web routes
└── tests/
    ├── Feature/              # Integration tests
    └── Unit/                 # Unit tests
```

---

## Key Features Summary

### ✅ Implemented Features

- ✅ Employee master data management
- ✅ Organizational structure (departments, positions)
- ✅ Time & attendance tracking (web + mobile)
- ✅ Leave management with approval workflows
- ✅ Payroll processing (BPJS, PPh 21 calculations)
- ✅ Payslip generation and ESS access
- ✅ Reimbursement claims
- ✅ Client visit tracking
- ✅ Approval workflow engine
- ✅ Announcements system
- ✅ Push notifications
- ✅ Team status viewing
- ✅ Recruitment (ATS) pipeline
- ✅ Performance management (KPI/OKR)
- ✅ Training records tracking
- ✅ HR analytics dashboard

### 🔄 Workflow States

**Leave Requests**: draft → pending → approved/rejected → cancelled

**Reimbursements**: pending → approved/rejected → paid

**Payrun Batches**: draft → calculated → approved → paid

**Applicants**: applied → shortlisted → interviewed → offered → hired

**Client Visits**: planned → in_progress → completed → cancelled

---

## Security & Permissions

- **Filament Shield**: Role-based permissions
- **Spatie Permission**: Fine-grained access control
- **Policies**: Resource-level authorization
- **API Authentication**: Sanctum tokens
- **Password Protection**: Payslip PDFs protected with employee NIK/DOB

---

## Integration Points

- **Google OAuth**: Employee authentication
- **Push Notifications**: FCM for mobile alerts
- **Email Notifications**: Queue-based email system
- **File Storage**: Private storage for documents/receipts
- **Geocoding**: Reverse geocoding for addresses

---

**Last Updated**: January 25, 2026  
**Version**: 1.0
