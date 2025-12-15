# Requirements Document

## Introduction

This document specifies the requirements for Phase 4 of the HRIS module: Talent Management & Analytics. This phase implements recruitment/applicant tracking (ATS), performance management (KPI/OKR), training & development tracking, and HR analytics dashboards. The system provides comprehensive talent lifecycle management from hiring through performance evaluation and professional development.

## Glossary

- **Talent_System**: The talent management and analytics module within the HRIS system
- **Job_Posting**: A published vacancy announcement with position details and requirements
- **Applicant**: A candidate who has applied for a job posting
- **ATS**: Applicant Tracking System - workflow for managing recruitment pipeline
- **Interview**: A scheduled evaluation session between an applicant and interviewer(s)
- **Performance_Cycle**: A defined period for performance evaluation (e.g., annual, quarterly)
- **Performance_Goal**: A measurable objective assigned to an employee within a cycle
- **KPI**: Key Performance Indicator - quantifiable measure of performance
- **OKR**: Objectives and Key Results - goal-setting framework
- **Performance_Review**: A formal evaluation of an employee's performance for a cycle
- **Training_Record**: Documentation of employee training completion and certifications
- **HR_Dashboard**: Visual analytics interface showing key HR metrics
- **Headcount**: Total number of active employees
- **Turnover_Rate**: Percentage of employees who leave within a period
- **Reviewer**: A user (typically manager) who evaluates employee performance

## Requirements

### Requirement 1: Job Posting Management

**User Story:** As an HR administrator, I want to create and manage job postings, so that I can advertise open positions and attract candidates.

#### Acceptance Criteria

1. WHEN an HR administrator creates a job posting THEN the Talent_System SHALL store title, department_id, location, employment_type, description, and requirements
2. WHEN a job posting is created THEN the Talent_System SHALL set initial status to 'draft'
3. WHEN an HR administrator publishes a job posting THEN the Talent_System SHALL update status to 'published' and record published_at timestamp
4. WHEN an HR administrator closes a job posting THEN the Talent_System SHALL update status to 'closed' and record closed_at timestamp
5. WHEN listing job postings THEN the Talent_System SHALL filter by status and department
6. WHEN a job posting has applicants THEN the Talent_System SHALL display applicant count

### Requirement 2: Applicant Tracking

**User Story:** As an HR recruiter, I want to track applicants through the hiring pipeline, so that I can manage the recruitment process efficiently.

#### Acceptance Criteria

1. WHEN an applicant applies THEN the Talent_System SHALL store name, email, phone, resume_file_path, and job_posting_id
2. WHEN an applicant is created THEN the Talent_System SHALL set initial status to 'applied'
3. WHEN an HR recruiter advances an applicant THEN the Talent_System SHALL update status following the pipeline: applied → shortlisted → interviewed → offered → hired
4. WHEN an HR recruiter rejects an applicant THEN the Talent_System SHALL update status to 'rejected' and store rejection_reason
5. WHEN an applicant is hired THEN the Talent_System SHALL allow linking to a new employee record
6. WHEN listing applicants THEN the Talent_System SHALL filter by job_posting_id and status
7. WHEN uploading resume THEN the Talent_System SHALL store file in private storage with secure access

### Requirement 3: Interview Scheduling

**User Story:** As an HR recruiter, I want to schedule and track interviews, so that I can coordinate the evaluation process.

#### Acceptance Criteria

1. WHEN scheduling an interview THEN the Talent_System SHALL store applicant_id, interviewer_id, scheduled_at, location, and interview_type
2. WHEN an interview is scheduled THEN the Talent_System SHALL set initial result to 'pending'
3. WHEN an interviewer completes an interview THEN the Talent_System SHALL allow recording result (pass, fail) and notes
4. WHEN an interview is scheduled THEN the Talent_System SHALL notify the interviewer via the notification system
5. WHEN listing interviews THEN the Talent_System SHALL filter by applicant_id, interviewer_id, and date range
6. WHEN an applicant has multiple interviews THEN the Talent_System SHALL display all interview results in chronological order

### Requirement 4: Performance Cycle Management

**User Story:** As an HR administrator, I want to define performance evaluation cycles, so that I can structure the review process.

#### Acceptance Criteria

1. WHEN creating a performance cycle THEN the Talent_System SHALL store name, period_start_date, period_end_date, and description
2. WHEN a performance cycle is created THEN the Talent_System SHALL set initial status to 'draft'
3. WHEN an HR administrator activates a cycle THEN the Talent_System SHALL update status to 'active'
4. WHEN an HR administrator closes a cycle THEN the Talent_System SHALL update status to 'closed' and prevent further goal/review modifications
5. WHEN listing performance cycles THEN the Talent_System SHALL display status, date range, and participant count
6. IF a performance cycle overlaps with an existing active cycle THEN the Talent_System SHALL warn the administrator

### Requirement 5: Performance Goal Setting

**User Story:** As a manager, I want to set performance goals for my team members, so that I can define clear expectations and track progress.

#### Acceptance Criteria

1. WHEN creating a performance goal THEN the Talent_System SHALL store employee_id, cycle_id, title, description, weight, and target_value
2. WHEN a goal is created THEN the Talent_System SHALL validate that total weight for an employee in a cycle does not exceed 100%
3. WHEN updating goal progress THEN the Talent_System SHALL store actual_value and calculate score based on target achievement
4. WHEN listing goals THEN the Talent_System SHALL filter by employee_id and cycle_id
5. WHEN a cycle is closed THEN the Talent_System SHALL prevent modifications to associated goals
6. WHEN calculating goal score THEN the Talent_System SHALL use formula: (actual_value / target_value) * weight

### Requirement 6: Performance Review

**User Story:** As a manager, I want to conduct performance reviews for my team members, so that I can provide formal feedback and ratings.

#### Acceptance Criteria

1. WHEN creating a performance review THEN the Talent_System SHALL store employee_id, cycle_id, reviewer_id, overall_score, and comments
2. WHEN calculating overall score THEN the Talent_System SHALL aggregate weighted goal scores for the employee in the cycle
3. WHEN a review is submitted THEN the Talent_System SHALL record submitted_at timestamp
4. WHEN listing reviews THEN the Talent_System SHALL filter by employee_id, cycle_id, and reviewer_id
5. WHEN an employee has multiple reviewers THEN the Talent_System SHALL support multiple reviews per cycle
6. WHEN a cycle is closed THEN the Talent_System SHALL prevent new reviews from being created

### Requirement 7: Training & Development

**User Story:** As an HR administrator, I want to track employee training and certifications, so that I can manage professional development.

#### Acceptance Criteria

1. WHEN creating a training record THEN the Talent_System SHALL store employee_id, training_name, provider, completion_date, and certificate_file_path
2. WHEN a training has an expiry THEN the Talent_System SHALL store expiry_date and notify before expiration
3. WHEN listing training records THEN the Talent_System SHALL filter by employee_id and completion status
4. WHEN uploading certificate THEN the Talent_System SHALL store file in private storage with secure access
5. WHEN a certification is expiring within 30 days THEN the Talent_System SHALL generate a notification for the employee and HR

### Requirement 8: HR Analytics Dashboard

**User Story:** As an HR manager, I want to view HR analytics and KPIs, so that I can make data-driven decisions.

#### Acceptance Criteria

1. WHEN accessing the HR dashboard THEN the Talent_System SHALL display current headcount by department and employment status
2. WHEN calculating turnover rate THEN the Talent_System SHALL compute (employees_left / average_headcount) * 100 for the selected period
3. WHEN displaying age distribution THEN the Talent_System SHALL group employees by age brackets (under 25, 25-34, 35-44, 45-54, 55+)
4. WHEN displaying tenure distribution THEN the Talent_System SHALL group employees by years of service
5. WHEN displaying leave usage THEN the Talent_System SHALL show leave days used vs available by leave type
6. WHEN displaying attendance KPIs THEN the Talent_System SHALL show attendance rate, late percentage, and average working hours

### Requirement 9: Analytics Export

**User Story:** As an HR manager, I want to export HR data and reports, so that I can share insights with management and comply with audits.

#### Acceptance Criteria

1. WHEN exporting headcount report THEN the Talent_System SHALL generate CSV/XLSX with employee details grouped by department
2. WHEN exporting turnover report THEN the Talent_System SHALL include hire dates, termination dates, and reasons
3. WHEN exporting performance summary THEN the Talent_System SHALL include cycle, employee, goals, and scores
4. WHEN exporting recruitment report THEN the Talent_System SHALL include job postings, applicant counts, and time-to-hire metrics
5. WHEN generating exports THEN the Talent_System SHALL apply date range filters

### Requirement 10: Filament Admin Resources

**User Story:** As an HR administrator, I want to manage talent data through the admin panel, so that I can efficiently handle recruitment, performance, and training.

#### Acceptance Criteria

1. WHEN accessing JobPostingResource THEN the Talent_System SHALL display title, department, status, and applicant count
2. WHEN accessing ApplicantResource THEN the Talent_System SHALL provide actions for advance, reject, and schedule interview
3. WHEN accessing InterviewResource THEN the Talent_System SHALL display applicant, interviewer, scheduled time, and result
4. WHEN accessing PerformanceCycleResource THEN the Talent_System SHALL display name, date range, status, and goal count
5. WHEN accessing PerformanceGoalResource THEN the Talent_System SHALL display employee, cycle, title, weight, and progress
6. WHEN accessing PerformanceReviewResource THEN the Talent_System SHALL display employee, cycle, reviewer, and overall score
7. WHEN accessing TrainingRecordResource THEN the Talent_System SHALL display employee, training name, completion date, and expiry status

### Requirement 11: Authorization and Security

**User Story:** As a system administrator, I want talent data to be properly secured, so that sensitive recruitment and performance information is protected.

#### Acceptance Criteria

1. WHEN accessing job postings THEN the Talent_System SHALL restrict create/edit to hr-admin role
2. WHEN accessing applicant data THEN the Talent_System SHALL restrict to hr-admin and hr-staff roles
3. WHEN accessing performance goals THEN the Talent_System SHALL allow managers to view/edit goals for their direct reports only
4. WHEN accessing performance reviews THEN the Talent_System SHALL allow employees to view their own reviews only
5. WHEN accessing training records THEN the Talent_System SHALL allow employees to view their own records, hr-admin to view all
6. WHEN accessing HR analytics THEN the Talent_System SHALL restrict to hr-admin and hr-manager roles