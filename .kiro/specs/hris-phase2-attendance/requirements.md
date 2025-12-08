# Requirements Document

## Introduction

This specification covers the completion of HRIS Phase 2, focusing on the Time & Attendance system and the Approval Workflow Engine. The attendance system enables employees to check-in and check-out via web and mobile interfaces with optional GPS coordinate capture. The approval workflow engine provides configurable multi-step approval routing for leave requests, reimbursements, and other approvable items.

This builds upon the existing Phase 1 implementation (Core HR + Org Structure) and the partially completed leave management system.

## Glossary

-   **Attendance_System**: The module responsible for recording employee check-in and check-out times, locations, and attendance status.
-   **AttendanceLog**: A record of a single day's attendance for an employee, including check-in/out times and GPS coordinates.
-   **AttendanceService**: The service class that handles attendance business logic including check-in, check-out, and status calculations.
-   **ApprovalWorkflow**: A configurable template defining the sequence of approval steps for a specific module (leave, reimbursement, etc.).
-   **ApprovalStep**: A materialized instance of a workflow step for a specific approvable item, tracking the approver and status.
-   **GPS_Coordinates**: Latitude and longitude values captured during attendance check-in/check-out for location verification.
-   **Attendance_Status**: The computed status of an employee's attendance (present, late, half_day, absent, leave, holiday).
-   **Check_In**: The action of recording an employee's arrival at work with timestamp and optional location.
-   **Check_Out**: The action of recording an employee's departure from work with timestamp and optional location.
-   **Timestamped_Photo**: A photo captured during check-in/check-out with an overlay showing the clock status, time, and readable address.
-   **Reverse_Geocoding**: The process of converting GPS coordinates (latitude/longitude) into a human-readable address format.
-   **Readable_Address**: A formatted address string containing building name, street, and city (e.g., "Treasury Tower Office, Jl. Tulodong atas, Jakarta Selatan").
-   **Work_From_Home (WFH)**: An attendance mode where an employee works remotely from home instead of the office.
-   **WFH_Quota**: The weekly allowance for Work From Home days, limited to 1 day per week and non-cumulative.
-   **Attendance_Mode**: The type of attendance being recorded (office or wfh).

## Requirements

### Requirement 1

**User Story:** As an employee, I want to check in and check out via web or mobile, so that my attendance is accurately recorded.

#### Acceptance Criteria

1. WHEN an employee initiates a check-in action THEN the Attendance_System SHALL create an AttendanceLog record with the current timestamp and source (web/mobile)
2. WHEN an employee initiates a check-out action THEN the Attendance_System SHALL update the existing AttendanceLog record with the check-out timestamp
3. WHEN an employee attempts to check-in while already checked-in for the day THEN the Attendance_System SHALL reject the action and return an appropriate error message
4. WHEN an employee attempts to check-out without a prior check-in for the day THEN the Attendance_System SHALL reject the action and return an appropriate error message
5. WHEN GPS coordinates are provided during check-in THEN the Attendance_System SHALL store the latitude and longitude values in the AttendanceLog
6. WHEN GPS coordinates are provided during check-out THEN the Attendance_System SHALL store the latitude and longitude values in the AttendanceLog
7. WHEN a photo is captured during check-in THEN the Attendance_System SHALL store the photo with a timestamp overlay in the format "Clock in: HH:MM" and readable address "Building name, Street, City"
8. WHEN a photo is captured during check-out THEN the Attendance_System SHALL store the photo with a timestamp overlay in the format "Clock out: HH:MM" and readable address "Building name, Street, City"
9. WHEN GPS coordinates are provided THEN the Attendance_System SHALL perform reverse geocoding to obtain a human-readable address containing building name, street, and city
10. WHEN an employee initiates check-in THEN the Attendance_System SHALL allow the employee to select an Attendance_Mode (office or wfh)
11. WHEN an employee selects WFH mode THEN the Attendance_System SHALL verify the employee has remaining WFH_Quota for the current week
12. WHEN an employee has already used their WFH_Quota for the current week THEN the Attendance_System SHALL reject the WFH check-in and return an appropriate error message
13. WHEN a WFH check-in is successful THEN the Attendance_System SHALL decrement the employee's WFH_Quota for the current week
14. WHILE calculating WFH_Quota THEN the Attendance_System SHALL reset the quota to 1 at the start of each week (Monday) without carrying over unused quota from the previous week

### Requirement 2

**User Story:** As an employee, I want to view my attendance history, so that I can track my work patterns and verify my records.

#### Acceptance Criteria

1. WHEN an employee requests their daily attendance THEN the Attendance_System SHALL return the AttendanceLog for the specified date including check-in time, check-out time, and status
2. WHEN an employee requests their attendance history for a date range THEN the Attendance_System SHALL return all AttendanceLogs within that range ordered by date descending
3. WHEN displaying attendance history THEN the Attendance_System SHALL include the computed status (present, late, half_day, absent, leave, holiday) for each day

### Requirement 3

**User Story:** As an HR administrator, I want to view and manage employee attendance records, so that I can audit attendance and handle exceptions.

#### Acceptance Criteria

1. WHEN an HR administrator accesses the attendance resource THEN the Attendance_System SHALL display a filterable list of all employee AttendanceLogs
2. WHEN an HR administrator filters attendance by employee, date range, or status THEN the Attendance_System SHALL return only matching records
3. WHEN an HR administrator views an AttendanceLog THEN the Attendance_System SHALL display all fields including GPS coordinates and source
4. WHEN an HR administrator exports attendance data THEN the Attendance_System SHALL generate a downloadable file in the requested format (CSV/XLSX)

### Requirement 4

**User Story:** As a system administrator, I want to configure approval workflows, so that leave requests and reimbursements follow the correct approval chain.

#### Acceptance Criteria

1. WHEN a system administrator creates an ApprovalWorkflow THEN the Attendance_System SHALL store the workflow name, module type, and step definitions
2. WHEN defining workflow steps THEN the Attendance_System SHALL support both role-based (e.g., head_division, hr-admin) and user-based approver assignments
3. WHEN an ApprovalWorkflow is updated THEN the Attendance_System SHALL apply changes only to new requests, preserving existing request workflows

### Requirement 5

**User Story:** As an approver, I want to approve or reject requests assigned to me, so that the approval workflow progresses correctly.

#### Acceptance Criteria

1. WHEN a leave request or reimbursement is submitted THEN the Attendance_System SHALL instantiate ApprovalSteps based on the applicable ApprovalWorkflow
2. WHEN an approver approves a step THEN the Attendance_System SHALL mark the current step as approved and advance to the next step
3. WHEN the final approval step is approved THEN the Attendance_System SHALL mark the request as approved and trigger any post-approval actions
4. WHEN an approver rejects a step THEN the Attendance_System SHALL mark the request as rejected and record the rejection reason
5. WHEN a request is pending approval THEN the Attendance_System SHALL notify the current approver via the existing notification system

### Requirement 6

**User Story:** As a manager, I want to view my team's attendance summary, so that I can monitor team presence and identify patterns.

#### Acceptance Criteria

1. WHEN a manager accesses team attendance THEN the Attendance_System SHALL display attendance summaries for all direct reports
2. WHEN viewing team attendance THEN the Attendance_System SHALL show aggregated statistics including present days, late days, and absent days for the selected period
