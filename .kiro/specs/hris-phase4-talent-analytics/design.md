# Design Document

## Overview

This design document describes the architecture and implementation for HRIS Phase 4: Talent Management & Analytics. The system provides comprehensive talent lifecycle management including recruitment/ATS, performance management (KPI/OKR), training tracking, and HR analytics dashboards with exportable reports.

## Architecture

The talent module follows the existing HRIS architecture pattern:

```
┌─────────────────────────────────────────────────────────────────┐
│                      Filament Admin Panel                        │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐   │
│  │JobPosting       │ │PerformanceCycle │ │TrainingRecord   │   │
│  │Resource         │ │Resource         │ │Resource         │   │
│  └────────┬────────┘ └────────┬────────┘ └────────┬────────┘   │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐   │
│  │Applicant        │ │PerformanceGoal  │ │HRDashboard      │   │
│  │Resource         │ │Resource         │ │Page             │   │
│  └────────┬────────┘ └────────┬────────┘ └────────┬────────┘   │
└───────────┼────────────────────┼────────────────────┼───────────┘
            │                    │                    │
┌───────────┼────────────────────┼────────────────────┼───────────┐
│           ▼                    ▼                    ▼           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    Service Layer                         │   │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────────┐    │   │
│  │  │Recruitment  │ │Performance  │ │HRAnalytics      │    │   │
│  │  │Service      │ │Service      │ │Service          │    │   │
│  │  └─────────────┘ └─────────────┘ └─────────────────┘    │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
            │                    │                    │
┌───────────┼────────────────────┼────────────────────┼───────────┐
│           ▼                    ▼                    ▼           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    Model Layer                           │   │
│  │  JobPosting  Applicant  Interview  PerformanceCycle     │   │
│  │  PerformanceGoal  PerformanceReview  TrainingRecord     │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### Models

#### JobPosting

- `id`, `title`, `department_id`, `location`, `employment_type`
- `description` (text), `requirements` (text)
- `status` (enum: draft, published, closed)
- `published_at`, `closed_at`
- Relationships: belongsTo Department, hasMany Applicant

#### Applicant

- `id`, `job_posting_id`, `name`, `email`, `phone`
- `resume_file_path`, `cover_letter` (text)
- `status` (enum: applied, shortlisted, interviewed, offered, hired, rejected)
- `rejection_reason` (text, nullable)
- `hired_employee_id` (FK → employees, nullable)
- Relationships: belongsTo JobPosting, hasMany Interview

#### Interview

- `id`, `applicant_id`, `interviewer_id` (FK → users)
- `scheduled_at` (datetime), `location`, `interview_type` (enum: phone, video, onsite)
- `result` (enum: pending, pass, fail)
- `notes` (text)
- Relationships: belongsTo Applicant, belongsTo User (interviewer)

#### PerformanceCycle

- `id`, `name`, `description`
- `period_start_date`, `period_end_date`
- `status` (enum: draft, active, closed)
- Relationships: hasMany PerformanceGoal, hasMany PerformanceReview

#### PerformanceGoal

- `id`, `employee_id`, `cycle_id`, `title`, `description`
- `weight` (decimal, 0-100), `target_value` (decimal)
- `actual_value` (decimal, nullable), `score` (decimal, nullable)
- Relationships: belongsTo Employee, belongsTo PerformanceCycle

#### PerformanceReview

- `id`, `employee_id`, `cycle_id`, `reviewer_id` (FK → users)
- `overall_score` (decimal), `comments` (text)
- `submitted_at` (datetime, nullable)
- Relationships: belongsTo Employee, belongsTo PerformanceCycle, belongsTo User (reviewer)

#### TrainingRecord

- `id`, `employee_id`, `training_name`, `provider`
- `description` (text), `completion_date`, `expiry_date` (nullable)
- `certificate_file_path` (nullable), `status` (enum: planned, in_progress, completed)
- Relationships: belongsTo Employee

### Services

#### RecruitmentService

```php
class RecruitmentService
{
    public function publishJobPosting(JobPosting $posting): void;
    public function closeJobPosting(JobPosting $posting): void;
    public function advanceApplicant(Applicant $applicant, string $newStatus): void;
    public function rejectApplicant(Applicant $applicant, string $reason): void;
    public function scheduleInterview(Applicant $applicant, array $data): Interview;
    public function recordInterviewResult(Interview $interview, string $result, ?string $notes): void;
    public function hireApplicant(Applicant $applicant, Employee $employee): void;
}
```

#### PerformanceService

```php
class PerformanceService
{
    public function activateCycle(PerformanceCycle $cycle): void;
    public function closeCycle(PerformanceCycle $cycle): void;
    public function createGoal(array $data): PerformanceGoal;
    public function updateGoalProgress(PerformanceGoal $goal, float $actualValue): void;
    public function calculateGoalScore(PerformanceGoal $goal): float;
    public function calculateOverallScore(Employee $employee, PerformanceCycle $cycle): float;
    public function submitReview(PerformanceReview $review): void;
    public function validateGoalWeight(int $employeeId, int $cycleId, float $newWeight): bool;
}
```

#### HRAnalyticsService

```php
class HRAnalyticsService
{
    public function getHeadcountByDepartment(): Collection;
    public function getHeadcountByEmploymentStatus(): Collection;
    public function calculateTurnoverRate(Carbon $startDate, Carbon $endDate): float;
    public function getAgeDistribution(): array;
    public function getTenureDistribution(): array;
    public function getLeaveUsageSummary(int $year): Collection;
    public function getAttendanceKPIs(Carbon $startDate, Carbon $endDate): array;
    public function getRecruitmentMetrics(Carbon $startDate, Carbon $endDate): array;
}
```

## Data Models

### Database Schema

```sql
-- job_postings
CREATE TABLE job_postings (
    id BIGINT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    department_id BIGINT REFERENCES departments(id),
    location VARCHAR(255),
    employment_type ENUM('full_time', 'part_time', 'contract', 'intern') NOT NULL,
    description TEXT,
    requirements TEXT,
    status ENUM('draft', 'published', 'closed') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    closed_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (status),
    INDEX (department_id)
);

-- applicants
CREATE TABLE applicants (
    id BIGINT PRIMARY KEY,
    job_posting_id BIGINT REFERENCES job_postings(id),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50),
    resume_file_path VARCHAR(255),
    cover_letter TEXT,
    status ENUM('applied', 'shortlisted', 'interviewed', 'offered', 'hired', 'rejected') DEFAULT 'applied',
    rejection_reason TEXT,
    hired_employee_id BIGINT REFERENCES employees(id) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (job_posting_id),
    INDEX (status)
);

-- interviews
CREATE TABLE interviews (
    id BIGINT PRIMARY KEY,
    applicant_id BIGINT REFERENCES applicants(id),
    interviewer_id BIGINT REFERENCES users(id),
    scheduled_at DATETIME NOT NULL,
    location VARCHAR(255),
    interview_type ENUM('phone', 'video', 'onsite') DEFAULT 'onsite',
    result ENUM('pending', 'pass', 'fail') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (applicant_id),
    INDEX (interviewer_id),
    INDEX (scheduled_at)
);
```

-- performance_cycles
CREATE TABLE performance_cycles (
id BIGINT PRIMARY KEY,
name VARCHAR(255) NOT NULL,
description TEXT,
period_start_date DATE NOT NULL,
period_end_date DATE NOT NULL,
status ENUM('draft', 'active', 'closed') DEFAULT 'draft',
created_at TIMESTAMP,
updated_at TIMESTAMP,
INDEX (status),
INDEX (period_start_date, period_end_date)
);

-- performance_goals
CREATE TABLE performance_goals (
id BIGINT PRIMARY KEY,
employee_id BIGINT REFERENCES employees(id),
cycle_id BIGINT REFERENCES performance_cycles(id),
title VARCHAR(255) NOT NULL,
description TEXT,
weight DECIMAL(5,2) NOT NULL,
target_value DECIMAL(15,2) NOT NULL,
actual_value DECIMAL(15,2) NULL,
score DECIMAL(5,2) NULL,
created_at TIMESTAMP,
updated_at TIMESTAMP,
INDEX (employee_id),
INDEX (cycle_id)
);

-- performance_reviews
CREATE TABLE performance_reviews (
id BIGINT PRIMARY KEY,
employee_id BIGINT REFERENCES employees(id),
cycle_id BIGINT REFERENCES performance_cycles(id),
reviewer_id BIGINT REFERENCES users(id),
overall_score DECIMAL(5,2),
comments TEXT,
submitted_at TIMESTAMP NULL,
created_at TIMESTAMP,
updated_at TIMESTAMP,
INDEX (employee_id),
INDEX (cycle_id),
INDEX (reviewer_id)
);

-- training_records
CREATE TABLE training_records (
id BIGINT PRIMARY KEY,
employee_id BIGINT REFERENCES employees(id),
training_name VARCHAR(255) NOT NULL,
provider VARCHAR(255),
description TEXT,
completion_date DATE,
expiry_date DATE NULL,
certificate_file_path VARCHAR(255) NULL,
status ENUM('planned', 'in_progress', 'completed') DEFAULT 'planned',
created_at TIMESTAMP,
updated_at TIMESTAMP,
INDEX (employee_id),
INDEX (expiry_date)
);

````

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Job posting data persistence round-trip
*For any* valid job posting with title, department, location, employment_type, and description, storing and retrieving the posting SHALL return equivalent data.
**Validates: Requirements 1.1**

### Property 2: Job posting status workflow
*For any* job posting, the status SHALL transition only in valid paths: draft → published → closed, and each transition SHALL record the appropriate timestamp.
**Validates: Requirements 1.2, 1.3, 1.4**

### Property 3: Applicant status workflow
*For any* applicant, the status SHALL transition only in valid paths: applied → shortlisted → interviewed → offered → hired, or any status → rejected.
**Validates: Requirements 2.2, 2.3, 2.4**

### Property 4: Applicant filtering by job posting and status
*For any* filter query with job_posting_id and/or status, the returned applicants SHALL match all specified filter criteria.
**Validates: Requirements 2.6**

### Property 5: Interview scheduling data persistence
*For any* scheduled interview with applicant, interviewer, scheduled_at, and interview_type, storing and retrieving SHALL return equivalent data with initial result 'pending'.
**Validates: Requirements 3.1, 3.2**

### Property 6: Interview result recording
*For any* completed interview, recording a result (pass/fail) and notes SHALL persist the data and update the interview record.
**Validates: Requirements 3.3**

### Property 7: Performance cycle status workflow
*For any* performance cycle, the status SHALL transition only in the order: draft → active → closed.
**Validates: Requirements 4.2, 4.3, 4.4**

### Property 8: Performance cycle immutability after closure
*For any* closed performance cycle, attempts to modify associated goals or create new reviews SHALL be rejected.
**Validates: Requirements 4.4, 5.5, 6.6**

### Property 9: Performance goal weight validation
*For any* employee in a performance cycle, the sum of all goal weights SHALL not exceed 100%.
**Validates: Requirements 5.2**

### Property 10: Performance goal score calculation
*For any* performance goal with target_value and actual_value, the score SHALL equal (actual_value / target_value) * weight.
**Validates: Requirements 5.6**

### Property 11: Performance review overall score aggregation
*For any* performance review, the overall_score SHALL equal the sum of weighted goal scores for the employee in the cycle.
**Validates: Requirements 6.2**

### Property 12: Training record data persistence
*For any* training record with employee, training_name, provider, and completion_date, storing and retrieving SHALL return equivalent data.
**Validates: Requirements 7.1**

### Property 13: Headcount calculation accuracy
*For any* department, the headcount SHALL equal the count of active employees in that department.
**Validates: Requirements 8.1**

### Property 14: Turnover rate calculation
*For any* date range, the turnover rate SHALL equal (employees_left / average_headcount) * 100.
**Validates: Requirements 8.2**

### Property 15: Age distribution grouping
*For any* set of employees, the age distribution SHALL correctly group employees into brackets: under 25, 25-34, 35-44, 45-54, 55+.
**Validates: Requirements 8.3**

### Property 16: Role-based talent access control
*For any* user without hr-admin role, access to create/edit job postings SHALL be denied.
**Validates: Requirements 11.1, 11.2**

## Error Handling

### Validation Errors
- Invalid status transitions: Return 400 with current status and allowed transitions
- Goal weight exceeds 100%: Return 422 with validation error
- Closed cycle modification: Return 403 with immutability message
- Unauthorized access: Return 403 with permission denied message

### File Handling Errors
- Resume/certificate upload failure: Return 500, rollback record creation
- File not found: Return 404 with file reference

### Calculation Errors
- Division by zero in score calculation: Return 0, log warning
- Missing goal data: Skip in aggregation, log warning

## Testing Strategy

### Property-Based Testing Library
- **Pest PHP** with custom generators for talent data
- Minimum 100 iterations per property test

### Unit Tests
- RecruitmentService: Test status transitions and validations
- PerformanceService: Test score calculations and weight validation
- HRAnalyticsService: Test metric calculations

### Property-Based Tests
Each correctness property will be implemented as a property-based test with:
- Random job posting configurations
- Random applicant pipeline states
- Random goal weights and values
- Edge cases: zero values, boundary conditions

### Integration Tests
- Filament resource CRUD operations
- Export functionality
- Notification triggers

### Test Annotations
Each property-based test MUST include:
```php
/**
 * Feature: hris-phase4-talent-analytics, Property {number}: {property_text}
 * Validates: Requirements X.Y
 */
````
