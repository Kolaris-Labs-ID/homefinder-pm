# Implementation Plan

- [x]    1. Create database migrations and models for attendance system

     - [x] 1.1 Create attendance_logs migration with all columns (employee_id, date, mode, check_in/out fields, GPS, photo paths, status)
          - Include indexes on employee_id, date, status for query performance
          - _Requirements: 1.1, 1.2, 1.5, 1.6, 1.7, 1.8, 1.10_
     - [x] 1.2 Create AttendanceLog model with relationships, constants, and casts
          - Define STATUS*\* and MODE*\* constants
          - Add employee() relationship
          - Add scopes for filtering (forDate, forEmployee, forStatus)
          - _Requirements: 1.1, 1.10, 2.1_
     - [x] 1.3 Write property test for AttendanceLog model
          - **Property 5: GPS coordinates storage**
          - **Validates: Requirements 1.5, 1.6**
     - [x] 1.4 Create AttendanceLogFactory for testing
          - Generate realistic attendance data with GPS coordinates
          - _Requirements: 1.1_

- [x]    2. Implement AttendanceService core functionality

     - [x] 2.1 Create AttendanceService with checkIn method
          - Validate employee not already checked in
          - Create AttendanceLog with timestamp, source, mode
          - Store GPS coordinates if provided
          - _Requirements: 1.1, 1.3, 1.5, 1.10_
     - [x] 2.2 Write property test for check-in creation
          - **Property 1: Check-in creates AttendanceLog with correct data**
          - **Validates: Requirements 1.1, 1.10**
     - [x] 2.3 Write property test for duplicate check-in rejection
          - **Property 3: Duplicate check-in rejection**
          - **Validates: Requirements 1.3**
     - [x] 2.4 Implement checkOut method in AttendanceService
          - Validate employee has checked in
          - Update existing AttendanceLog with check-out data
          - _Requirements: 1.2, 1.4, 1.6_
     - [x] 2.5 Write property test for check-out updates
          - **Property 2: Check-out updates existing AttendanceLog**
          - **Validates: Requirements 1.2**
     - [x] 2.6 Write property test for check-out without check-in rejection
          - **Property 4: Check-out without check-in rejection**
          - **Validates: Requirements 1.4**

- [x]    3. Checkpoint - Ensure all tests pass

     - Ensure all tests pass, ask the user if questions arise.

- [x]    4. Implement WFH quota management

     - [x] 4.1 Add getRemainingWfhQuota method to AttendanceService
          - Calculate WFH days used in current week (Monday-Sunday)
          - Return 1 minus used days (minimum 0)
          - _Requirements: 1.11, 1.14_
     - [x] 4.2 Add WFH quota validation to checkIn method
          - Reject WFH check-in if quota exhausted
          - _Requirements: 1.11, 1.12_
     - [x] 4.3 Write property test for WFH quota validation
          - **Property 8: WFH quota validation**
          - **Validates: Requirements 1.11, 1.12**
     - [x] 4.4 Write property test for WFH quota decrement
          - **Property 9: WFH quota decrement**
          - **Validates: Requirements 1.13**
     - [x] 4.5 Write property test for WFH quota weekly reset
          - **Property 10: WFH quota weekly reset**
          - **Validates: Requirements 1.14**

- [x]    5. Implement GeocodingService for reverse geocoding

     - [x] 5.1 Create GeocodingService with reverseGeocode method
          - Integrate with geocoding API (OpenCage, Nominatim, or Google Maps)
          - Return formatted address: "Building, Street, City"
          - Handle API failures gracefully
          - _Requirements: 1.9_
     - [x] 5.2 Write property test for reverse geocoding
          - **Property 7: Reverse geocoding produces readable address**
          - **Validates: Requirements 1.9**

- [x]    6. Implement PhotoService for timestamped photos

     - [x] 6.1 Create PhotoService with processAttendancePhoto method
          - Add text overlay with clock type, time (HH:MM), and address
          - Store processed photo in storage/app/hris/attendance
          - Return stored file path
          - _Requirements: 1.7, 1.8_
     - [x] 6.2 Write property test for photo storage with overlay
          - **Property 6: Photo storage with timestamp overlay**
          - **Validates: Requirements 1.7, 1.8**

- [x]    7. Integrate services into AttendanceService

     - [x] 7.1 Inject GeocodingService and PhotoService into AttendanceService
          - Call reverseGeocode when GPS provided
          - Call processAttendancePhoto when photo provided
          - Store results in AttendanceLog
          - _Requirements: 1.7, 1.8, 1.9_

- [x]    8. Checkpoint - Ensure all tests pass

     - Ensure all tests pass, ask the user if questions arise.

- [x]    9. Implement attendance history and status computation

     - [x] 9.1 Add getTodayAttendance and getHistory methods to AttendanceService
          - Filter by employee and date range
          - Order by date descending
          - _Requirements: 2.1, 2.2_
     - [x] 9.2 Implement computeStatus method in AttendanceService
          - Determine status based on check-in time, check-out time, leave records
          - Return appropriate STATUS\_\* constant
          - _Requirements: 2.3_
     - [x] 9.3 Write property test for attendance history filtering
          - **Property 11: Attendance history filtering and ordering**
          - **Validates: Requirements 2.2**
     - [x] 9.4 Write property test for status computation
          - **Property 12: Attendance status computation**
          - **Validates: Requirements 2.3**

- [x]    10. Create Attendance API endpoints

     - [x] 10.1 Create AttendanceController with check-in endpoint
          - POST /api/hris/attendance/check-in
          - Accept latitude, longitude, source, mode, photo
          - Return created AttendanceLog
          - _Requirements: 1.1, 1.5, 1.7, 1.10_
     - [x] 10.2 Add check-out endpoint to AttendanceController
          - POST /api/hris/attendance/check-out
          - Accept latitude, longitude, photo
          - Return updated AttendanceLog
          - _Requirements: 1.2, 1.6, 1.8_
     - [x] 10.3 Add daily and history endpoints to AttendanceController
          - GET /api/hris/attendance/daily?date=YYYY-MM-DD
          - GET /api/hris/attendance/history?from=...&to=...
          - _Requirements: 2.1, 2.2_
     - [x] 10.4 Add WFH quota endpoint to AttendanceController
          - GET /api/hris/attendance/wfh-quota
          - Return remaining quota for current week
          - _Requirements: 1.11_
     - [x] 10.5 Register attendance routes in routes/api.php
          - Add routes under /api/hris/attendance prefix
          - Apply auth:sanctum middleware
          - _Requirements: 1.1, 2.1_
     - [x] 10.6 Write API integration tests for attendance endpoints
          - Test check-in, check-out, history, WFH quota endpoints
          - _Requirements: 1.1, 1.2, 2.1, 2.2_

- [x]    11. Checkpoint - Ensure all tests pass

     - Ensure all tests pass, ask the user if questions arise.

- [x]    12. Create approval workflow database structure

     - [x] 12.1 Create approval_workflows migration
          - Columns: name, for_module (enum), steps (json), is_active
          - _Requirements: 4.1, 4.2_
     - [x] 12.2 Create approval_steps migration
          - Columns: workflow_id, approvable_type/id (morph), step_order, approver_id, approver_role, status, approved_at, notes
          - _Requirements: 5.1_
     - [x] 12.3 Create ApprovalWorkflow model with casts and constants
          - Define MODULE\_\* constants
          - Cast steps to array
          - _Requirements: 4.1_
     - [x] 12.4 Create ApprovalStep model with relationships and constants
          - Define STATUS\_\* constants
          - Add approvable() morph relationship
          - Add approver() relationship
          - _Requirements: 5.1_
     - [x] 12.5 Write property test for approval workflow storage
          - **Property 14: Approval workflow storage**
          - **Validates: Requirements 4.1, 4.2**

- [x]    13. Implement ApprovalWorkflowService

     - [x] 13.1 Create ApprovalWorkflowService with initializeWorkflow method
          - Find applicable workflow for module
          - Create ApprovalStep records from workflow steps
          - Resolve role-based approvers to user IDs
          - Mark first step as pending
          - _Requirements: 5.1_
     - [x] 13.2 Write property test for workflow instantiation
          - **Property 15: Workflow instantiation**
          - **Validates: Requirements 5.1**
     - [x] 13.3 Implement approve method in ApprovalWorkflowService
          - Mark current step as approved
          - Advance to next step or mark approvable as approved
          - Send notification to next approver
          - _Requirements: 5.2, 5.3, 5.5_
     - [x] 13.4 Implement reject method in ApprovalWorkflowService
          - Mark step as rejected
          - Mark approvable as rejected
          - Record rejection reason
          - _Requirements: 5.4_
     - [x] 13.5 Write property test for approval progression
          - **Property 16: Approval progression**
          - **Validates: Requirements 5.2, 5.3**
     - [x] 13.6 Write property test for rejection termination
          - **Property 17: Rejection terminates workflow**
          - **Validates: Requirements 5.4**

- [x]    14. Integrate approval workflow with LeaveRequest

     - [x] 14.1 Update LeaveWorkflowService to use ApprovalWorkflowService
          - Call initializeWorkflow when leave request submitted
          - Use approval steps instead of simple approver assignment
          - _Requirements: 5.1_
     - [x] 14.2 Add approval_steps relationship to LeaveRequest model
          - Morph many relationship to ApprovalStep
          - _Requirements: 5.1_

- [x]    15. Checkpoint - Ensure all tests pass

     - Ensure all tests pass, ask the user if questions arise.

- [x]    16. Create Filament resources for HR administration

     - [x] 16.1 Create AttendanceLogResource for HR admin
          - Table with employee, date, mode, check-in/out times, status
          - Filters for employee, date range, status, mode
          - View page showing all fields including GPS and photos
          - _Requirements: 3.1, 3.2, 3.3_
     - [x] 16.2 Add export action to AttendanceLogResource
          - Export to CSV/XLSX using maatwebsite/excel
          - Include all relevant columns
          - _Requirements: 3.4_
     - [x] 16.3 Create ApprovalWorkflowResource for system admin
          - Form for name, module, steps configuration
          - Table listing all workflows
          - _Requirements: 4.1, 4.2, 4.3_
     - [x] 16.4 Write property test for HR filter accuracy
          - **Property 13: HR filter accuracy**
          - **Validates: Requirements 3.2**

- [x]    17. Implement team attendance for managers

     - [x] 17.1 Add getTeamAttendance method to AttendanceService
          - Get direct reports for manager
          - Return attendance summaries for team
          - _Requirements: 6.1_
     - [x] 17.2 Add getTeamStatistics method to AttendanceService
          - Aggregate present, late, absent days for period
          - _Requirements: 6.2_
     - [x] 17.3 Write property test for team attendance scope
          - **Property 18: Team attendance scope**
          - **Validates: Requirements 6.1**

- [x]    18. Create AttendanceLogPolicy for authorization

     - [x] 18.1 Create AttendanceLogPolicy with permission checks
          - HR admin can view all, employee can view own
          - Manager can view team attendance
          - _Requirements: 3.1, 6.1_
     - [x] 18.2 Register policy in AuthServiceProvider
          - _Requirements: 3.1_

- [x]    19. Final Checkpoint - Ensure all tests pass
     - Ensure all tests pass, ask the user if questions arise.
