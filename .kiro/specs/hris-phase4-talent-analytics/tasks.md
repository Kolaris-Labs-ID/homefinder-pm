# Implementation Plan

-   [x] 1.  Create database migrations and models for recruitment

                                  - [x] 1.1 Create job_postings migration with all columns
                                       - Include title, department_id, location, employment_type, description, requirements
                                       - Add status enum (draft, published, closed), published_at, closed_at
                                       - Add indexes on status and department_id
                                       - _Requirements: 1.1, 1.2_
                                  - [x] 1.2 Create JobPosting model with relationships, constants, and casts
                                       - Define STATUS\_\* constants (draft, published, closed)
                                       - Define EMPLOYMENT*TYPE*\* constants
                                       - Add department() and applicants() relationships
                                       - Add scopes for filtering by status
                                       - _Requirements: 1.1, 1.5_
                                  - [x] 1.3 Create JobPostingFactory for testing
                                       - _Requirements: 1.1_
                                  - [ ]\* 1.4 Write property test for job posting data persistence
                                       - **Property 1: Job posting data persistence round-trip**
                                       - **Validates: Requirements 1.1**

-   [x] 2. Create applicants migration and model

    -   [x] 2.1 Create applicants migration with all columns
        -   Include job_posting_id, name, email, phone, resume_file_path, cover_letter
        -   Add status enum (applied, shortlisted, interviewed, offered, hired, rejected)
        -   Add rejection_reason, hired_employee_id
        -   Add indexes on job_posting_id and status
        -   _Requirements: 2.1, 2.2_
    -   [x] 2.2 Create Applicant model with relationships and constants
        -   Define STATUS\_\* constants for pipeline stages
        -   Add jobPosting(), interviews(), hiredEmployee() relationships
        -   Add scopes for filtering
        -   _Requirements: 2.1, 2.6_
    -   [x] 2.3 Create ApplicantFactory for testing
        -   _Requirements: 2.1_
    -   [ ]\* 2.4 Write property test for applicant filtering
        -   **Property 4: Applicant filtering by job posting and status**
        -   **Validates: Requirements 2.6**

-   [x] 3. Create interviews migration and model

    -   [x] 3.1 Create interviews migration with all columns
        -   Include applicant_id, interviewer_id, scheduled_at, location, interview_type
        -   Add result enum (pending, pass, fail), notes
        -   Add indexes on applicant_id, interviewer_id, scheduled_at
        -   _Requirements: 3.1, 3.2_
    -   [x] 3.2 Create Interview model with relationships and constants
        -   Define RESULT*\* and TYPE*\* constants
        -   Add applicant() and interviewer() relationships
        -   _Requirements: 3.1_
    -   [x] 3.3 Create InterviewFactory for testing
        -   _Requirements: 3.1_
    -   [ ]\* 3.4 Write property test for interview data persistence
        -   **Property 5: Interview scheduling data persistence**
        -   **Validates: Requirements 3.1, 3.2**

-   [x] 4. Implement RecruitmentService

    -   [x] 4.1 Create RecruitmentService with job posting methods
        -   Implement publishJobPosting() with timestamp recording
        -   Implement closeJobPosting() with timestamp recording
        -   _Requirements: 1.3, 1.4_
    -   [ ]\* 4.2 Write property test for job posting status workflow
        -   **Property 2: Job posting status workflow**
        -   **Validates: Requirements 1.2, 1.3, 1.4**
    -   [x] 4.3 Implement applicant pipeline methods
        -   Implement advanceApplicant() with status validation
        -   Implement rejectApplicant() with reason storage
        -   Implement hireApplicant() with employee linking
        -   _Requirements: 2.3, 2.4, 2.5_
    -   [ ]\* 4.4 Write property test for applicant status workflow
        -   **Property 3: Applicant status workflow**
        -   **Validates: Requirements 2.2, 2.3, 2.4**
    -   [x] 4.5 Implement interview methods
        -   Implement scheduleInterview() with notification
        -   Implement recordInterviewResult()
        -   _Requirements: 3.3, 3.4_
    -   [ ]\* 4.6 Write property test for interview result recording
        -   **Property 6: Interview result recording**
        -   **Validates: Requirements 3.3**

-   [ ] 5. Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 6. Create database migrations and models for performance management

    -   [x] 6.1 Create performance_cycles migration
        -   Include name, description, period_start_date, period_end_date
        -   Add status enum (draft, active, closed)
        -   Add indexes on status and period dates
        -   _Requirements: 4.1, 4.2_
    -   [x] 6.2 Create PerformanceCycle model with relationships and constants
        -   Define STATUS\_\* constants
        -   Add goals() and reviews() relationships
        -   Add scope for active cycle
        -   _Requirements: 4.1, 4.5_
    -   [x] 6.3 Create PerformanceCycleFactory for testing
        -   _Requirements: 4.1_
    -   [x] 6.4 Create performance_goals migration
        -   Include employee_id, cycle_id, title, description
        -   Add weight, target_value, actual_value, score (decimals)
        -   Add indexes on employee_id and cycle_id
        -   _Requirements: 5.1_
    -   [x] 6.5 Create PerformanceGoal model with relationships
        -   Add employee() and cycle() relationships
        -   Add calculateScore() method
        -   _Requirements: 5.1, 5.6_
    -   [x] 6.6 Create PerformanceGoalFactory for testing
        -   _Requirements: 5.1_
    -   [x] 6.7 Create performance_reviews migration
        -   Include employee_id, cycle_id, reviewer_id
        -   Add overall_score, comments, submitted_at
        -   Add indexes
        -   _Requirements: 6.1_
    -   [x] 6.8 Create PerformanceReview model with relationships
        -   Add employee(), cycle(), reviewer() relationships
        -   _Requirements: 6.1_
    -   [x] 6.9 Create PerformanceReviewFactory for testing
        -   _Requirements: 6.1_

-   [x] 7. Implement PerformanceService

    -   [x] 7.1 Create PerformanceService with cycle management methods
        -   Implement activateCycle() and closeCycle()
        -   Add overlap warning check
        -   _Requirements: 4.3, 4.4, 4.6_
    -   [ ]\* 7.2 Write property test for performance cycle status workflow
        -   **Property 7: Performance cycle status workflow**
        -   **Validates: Requirements 4.2, 4.3, 4.4**
    -   [ ]\* 7.3 Write property test for cycle immutability
        -   **Property 8: Performance cycle immutability after closure**
        -   **Validates: Requirements 4.4, 5.5, 6.6**
    -   [x] 7.4 Implement goal management methods
        -   Implement createGoal() with weight validation
        -   Implement updateGoalProgress() with score calculation
        -   Implement validateGoalWeight()
        -   _Requirements: 5.2, 5.3, 5.6_
    -   [ ]\* 7.5 Write property test for goal weight validation
        -   **Property 9: Performance goal weight validation**
        -   **Validates: Requirements 5.2**
    -   [ ]\* 7.6 Write property test for goal score calculation
        -   **Property 10: Performance goal score calculation**
        -   **Validates: Requirements 5.6**
    -   [x] 7.7 Implement review methods
        -   Implement calculateOverallScore()
        -   Implement submitReview()
        -   _Requirements: 6.2, 6.3_
    -   [ ]\* 7.8 Write property test for review score aggregation
        -   **Property 11: Performance review overall score aggregation**
        -   **Validates: Requirements 6.2**

-   [ ] 8.  Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 9. Create database migrations and models for training

    -   [x] 9.1 Create training_records migration
        -   Include employee_id, training_name, provider, description
        -   Add completion_date, expiry_date, certificate_file_path
        -   Add status enum (planned, in_progress, completed)
        -   Add indexes on employee_id and expiry_date
        -   _Requirements: 7.1, 7.2_
    -   [x] 9.2 Create TrainingRecord model with relationships and constants
        -   Define STATUS\_\* constants
        -   Add employee() relationship
        -   Add scope for expiring certifications
        -   _Requirements: 7.1, 7.3_
    -   [x] 9.3 Create TrainingRecordFactory for testing
        -   _Requirements: 7.1_
    -   [ ]\* 9.4 Write property test for training record persistence
        -   **Property 12: Training record data persistence**
        -   **Validates: Requirements 7.1**

-   [x] 10. Implement HRAnalyticsService

    -   [x] 10.1 Create HRAnalyticsService with headcount methods
        -   Implement getHeadcountByDepartment()
        -   Implement getHeadcountByEmploymentStatus()
        -   _Requirements: 8.1_
    -   [ ]\* 10.2 Write property test for headcount calculation
        -   **Property 13: Headcount calculation accuracy**
        -   **Validates: Requirements 8.1**
    -   [x] 10.3 Implement turnover calculation
        -   Implement calculateTurnoverRate() with date range
        -   _Requirements: 8.2_
    -   [ ]\* 10.4 Write property test for turnover rate calculation
        -   **Property 14: Turnover rate calculation**
        -   **Validates: Requirements 8.2**
    -   [x] 10.5 Implement distribution methods
        -   Implement getAgeDistribution() with brackets
        -   Implement getTenureDistribution()
        -   _Requirements: 8.3, 8.4_
    -   [ ]\* 10.6 Write property test for age distribution grouping
        -   **Property 15: Age distribution grouping**
        -   **Validates: Requirements 8.3**
    -   [x] 10.7 Implement leave and attendance KPIs
        -   Implement getLeaveUsageSummary()
        -   Implement getAttendanceKPIs()
        -   _Requirements: 8.5, 8.6_
    -   [x] 10.8 Implement recruitment metrics
        -   Implement getRecruitmentMetrics() with time-to-hire
        -   _Requirements: 9.4_

-   [ ] 11. Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 12. Create Filament resources for recruitment

    -   [x] 12.1 Create JobPostingResource
        -   Form with title, department, location, employment_type, description, requirements
        -   Table with title, department, status, applicant count
        -   Add Publish and Close actions
        -   _Requirements: 10.1, 1.3, 1.4, 1.6_
    -   [x] 12.2 Create ApplicantResource
        -   Form with name, email, phone, resume upload, job posting select
        -   Table with name, job posting, status, applied date
        -   Add Advance, Reject, Schedule Interview actions
        -   _Requirements: 10.2, 2.3, 2.4_
    -   [x] 12.3 Create InterviewResource
        -   Form with applicant, interviewer, scheduled_at, location, type
        -   Table with applicant, interviewer, scheduled time, result
        -   Add Record Result action
        -   _Requirements: 10.3, 3.3_

-   [x] 13. Create Filament resources for performance management

    -   [x] 13.1 Create PerformanceCycleResource
        -   Form with name, description, period dates
        -   Table with name, date range, status, goal count
        -   Add Activate and Close actions
        -   _Requirements: 10.4, 4.3, 4.4_
    -   [x] 13.2 Create PerformanceGoalResource
        -   Form with employee, cycle, title, description, weight, target_value
        -   Table with employee, cycle, title, weight, progress
        -   Add Update Progress action
        -   _Requirements: 10.5, 5.3_
    -   [x] 13.3 Create PerformanceReviewResource
        -   Form with employee, cycle, reviewer, overall_score, comments
        -   Table with employee, cycle, reviewer, score, submitted status
        -   Add Submit action
        -   _Requirements: 10.6, 6.3_

-   [x] 14. Create Filament resources for training and dashboard

    -   [x] 14.1 Create TrainingRecordResource
        -   Form with employee, training_name, provider, dates, certificate upload
        -   Table with employee, training, completion date, expiry status
        -   Highlight expiring certifications
        -   _Requirements: 10.7, 7.2, 7.5_
    -   [x] 14.2 Create HRDashboardPage
        -   Headcount widgets by department and employment status
        -   Turnover rate chart with date range filter
        -   Age and tenure distribution charts
        -   Leave usage and attendance KPI widgets
        -   _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

-   [x] 15. Implement export functionality

    -   [x] 15.1 Create HeadcountExport class
        -   Export employee details grouped by department
        -   Support CSV/XLSX formats
        -   _Requirements: 9.1_
    -   [x] 15.2 Create TurnoverExport class
        -   Include hire dates, termination dates, reasons
        -   Support date range filter
        -   _Requirements: 9.2_
    -   [x] 15.3 Create PerformanceSummaryExport class
        -   Include cycle, employee, goals, scores
        -   _Requirements: 9.3_
    -   [x] 15.4 Create RecruitmentReportExport class
        -   Include job postings, applicant counts, time-to-hire
        -   _Requirements: 9.4_
    -   [x] 15.5 Add export actions to relevant resources
        -   Add export buttons to dashboard and resources
        -   _Requirements: 9.5_

-   [ ] 16. Checkpoint - Ensure all tests pass

    -   Ensure all tests pass, ask the user if questions arise.

-   [x] 17. Create policies for talent authorization

    -   [x] 17.1 Create JobPostingPolicy
        -   Restrict create/edit to hr-admin role
        -   _Requirements: 11.1_
    -   [x] 17.2 Create ApplicantPolicy
        -   Restrict to hr-admin and hr-staff roles
        -   _Requirements: 11.2_
    -   [x] 17.3 Create PerformanceGoalPolicy
        -   Allow managers to view/edit goals for direct reports
        -   _Requirements: 11.3_
    -   [x] 17.4 Create PerformanceReviewPolicy
        -   Allow employees to view own reviews only
        -   _Requirements: 11.4_
    -   [x] 17.5 Create TrainingRecordPolicy
        -   Allow employees to view own, hr-admin to view all
        -   _Requirements: 11.5_
    -   [x] 17.6 Create HRAnalyticsPolicy
        -   Restrict to hr-admin and hr-manager roles
        -   _Requirements: 11.6_
    -   [x] 17.7 Register policies in AuthServiceProvider
        -   _Requirements: 11.1-11.6_
    -   [ ]\* 17.8 Write property test for role-based access control
        -   **Property 16: Role-based talent access control**
        -   **Validates: Requirements 11.1, 11.2**

-   [x] 18. Implement certification expiry notifications

    -   [x] 18.1 Create CheckExpiringCertifications command
        -   Query certifications expiring within 30 days
        -   Send notifications to employee and HR
        -   _Requirements: 7.5_
    -   [x] 18.2 Schedule command in Console Kernel
        -   Run daily
        -   _Requirements: 7.5_

-   [x] 19. Final Checkpoint - Ensure all tests pass
    -   Ensure all tests pass, ask the user if questions arise.
