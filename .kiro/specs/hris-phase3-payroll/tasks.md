# Implementation Plan

- [x]    1. Create database migrations and models for salary structures
           - [x] 1.1 Create salary_structures migration with all columns
                - Include employee_id, effective_date, basic_salary, allowances_fixed (JSON), allowances_variable (JSON), deductions (JSON), pph21_method (enum), jkk_risk_level (enum)
                - Add indexes on employee_id and effective_date
                - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_
           - [x] 1.2 Create SalaryStructure model with relationships, constants, and casts
                - Define PPH21*METHOD*_ and JKK*RISK*_ constants
                - Add employee() relationship
                - Cast JSON fields to array
                - Add scope for active structure by date
                - _Requirements: 1.1, 1.6, 1.7_
           - [x] 1.3 Write property test for salary structure round-trip
                - **Property 1: Salary structure data persistence round-trip**
                - **Validates: Requirements 1.1, 1.2, 1.3, 1.4**
           - [x] 1.4 Create SalaryStructureFactory for testing
                - Generate realistic Indonesian salary data
                - _Requirements: 1.1_
           - [x] 1.5 Implement 75:25 rule validation in SalaryStructure model
                - Add validation that basic_salary >= 75% of (basic_salary + total_fixed_allowances)
                - _Requirements: 1.8_
           - [x] 1.6 Write property test for 75:25 rule validation
                - **Property 3: Salary structure 75:25 rule validation**
                - **Validates: Requirements 1.8**

- [x]    2. Implement BpjsCalculationService
           - [x] 2.1 Create BpjsCalculationService with constants and calculation methods
                - Define KESEHATAN_CEILING (12,000,000), JP_CEILING (10,547,400)
                - Define JKK_RATES mapping for risk levels
                - Implement calculateKesehatan(), calculateJht(), calculateJp(), calculateJkk(), calculateJkm()
                - _Requirements: 3.2, 3.3, 3.4, 3.5, 3.6_
           - [x] 2.2 Write property test for BPJS Kesehatan calculation
                - **Property 9: BPJS Kesehatan calculation with ceiling**
                - **Validates: Requirements 3.2**
           - [x] 2.3 Write property test for BPJS JHT calculation
                - **Property 10: BPJS JHT calculation without ceiling**
                - **Validates: Requirements 3.3**
           - [x] 2.4 Write property test for BPJS JP calculation
                - **Property 11: BPJS JP calculation with ceiling**
                - **Validates: Requirements 3.4**
           - [x] 2.5 Write property test for JKK risk level mapping
                - **Property 4: JKK risk level to rate mapping**
                - **Validates: Requirements 1.9**
           - [x] 2.6 Write property test for BPJS JKK and JKM calculation
                - **Property 12: BPJS JKK and JKM calculation**
                - **Validates: Requirements 3.5, 3.6**

- [x]    3. Checkpoint - Ensure all tests pass - Ensure all tests pass, ask the user if questions arise.

- [x]    4. Implement Pph21CalculationService
           - [x] 4.1 Create Pph21CalculationService with TER rate brackets
                - Define TER rate brackets based on annual taxable income
                - Implement calculateTer() and calculateMonthlyTax()
                - Support netto, gross, gross_up methods
                - _Requirements: 3.8_
           - [x] 4.2 Write unit tests for PPh 21 TER calculation
                - Test various income brackets
                - _Requirements: 3.8_

- [x]    5. Create database migrations and models for payrun batches
           - [x] 5.1 Create payrun_batches migration
                - Include period_start_date, period_end_date, pay_date, status (enum), notes
                - Add calculated_at, approved_at, approved_by, paid_at, paid_by
                - Add indexes on status and period dates
                - _Requirements: 2.1, 2.2_
           - [x] 5.2 Create PayrunBatch model with relationships and constants
                - Define STATUS\_\* constants (draft, calculated, approved, paid)
                - Add payrollRecords() relationship
                - Add approvedBy() and paidBy() relationships
                - _Requirements: 2.1, 2.2_
           - [x] 5.3 Create PayrunBatchFactory for testing
                - _Requirements: 2.1_

- [x]    6. Create database migrations and models for payroll records
           - [x] 6.1 Create payroll_records migration
                - Include all salary components, BPJS breakdowns, PPh 21, net income
                - Add details JSON column for full breakdown
                - Add unique constraint on (payrun_batch_id, employee_id)
                - _Requirements: 3.1, 3.10_
           - [x] 6.2 Create PayrollRecord model with relationships and casts
                - Add payrunBatch() and employee() relationships
                - Cast details to array
                - _Requirements: 3.1, 3.10_
           - [x] 6.3 Create PayrollRecordFactory for testing
                - _Requirements: 3.1_

- [x]    7. Implement PayrollCalculationService core functionality
           - [x] 7.1 Create PayrollCalculationService with getActiveSalaryStructure method
                - Find salary structure with most recent effective_date <= payrun date
                - _Requirements: 1.6, 1.7_
           - [x] 7.2 Write property test for active salary structure selection
                - **Property 2: Active salary structure selection**
                - **Validates: Requirements 1.6, 1.7**
           - [x] 7.3 Implement calculateEmployee method
                - Calculate gross income from salary structure
                - Call BpjsCalculationService for BPJS components
                - Call Pph21CalculationService for tax
                - Calculate net income
                - Store detailed breakdown
                - _Requirements: 3.1, 3.7, 3.9, 3.10_
           - [x] 7.4 Write property test for gross income calculation
                - **Property 8: Gross income calculation accuracy**
                - **Validates: Requirements 3.1**
           - [x] 7.5 Write property test for net income calculation
                - **Property 13: Net income calculation formula**
                - **Validates: Requirements 3.9**
           - [x] 7.6 Implement calculatePayrun method for batch processing
                - Get all active employees with valid salary structures
                - Create/update payroll records for each
                - Update batch status to 'calculated'
                - _Requirements: 2.3, 2.4_
           - [x] 7.7 Write property test for payrun calculation filtering
                - **Property 7: Payrun calculation processes only valid employees**
                - **Validates: Requirements 2.3**
           - [x] 7.8 Write property test for payrun recalculation idempotency
                - **Property 14: Payrun recalculation idempotency**
                - **Validates: Requirements 3.11**

- [x]    8. Checkpoint - Ensure all tests pass
           - Ensure all tests pass, ask the user if questions arise.

- [x]    9. Implement payrun batch workflow
           - [x] 9.1 Add approve and markAsPaid methods to PayrollCalculationService
                - Validate status transitions
                - Record timestamps and user IDs
                - _Requirements: 2.5, 2.6_
           - [x] 9.2 Write property test for payrun batch status workflow
                - **Property 5: Payrun batch status workflow**
                - **Validates: Requirements 2.2, 2.4, 2.5, 2.6**
           - [x] 9.3 Implement payroll record immutability for approved/paid batches
                - Add validation in PayrollRecord model (already implemented in Task 6)
                - _Requirements: 2.7_
           - [x] 9.4 Write property test for payrun batch immutability
                - **Property 6: Payrun batch immutability after approval**
                - **Validates: Requirements 2.7**

- [x]    10. Create database migrations and models for reimbursements

     - [x] 10.1 Create reimbursements migration
          - Include employee_id, type (enum), amount, description, receipt_file_path
          - Add status (enum), rejection_reason, paid_at, paid_by
          - Add indexes on employee_id and status
          - _Requirements: 4.1, 4.2_
     - [x] 10.2 Create Reimbursement model with relationships and constants
          - Define TYPE*\* and STATUS*\* constants
          - Add employee() relationship
          - Add morphMany approvalSteps() relationship
          - _Requirements: 4.1, 4.2, 4.3_
     - [x] 10.3 Create ReimbursementFactory for testing
          - _Requirements: 4.1_

- [x]    11. Implement ReimbursementService

     - [x] 11.1 Create ReimbursementService with create method
          - Store receipt file in private storage
          - Initialize approval workflow
          - _Requirements: 4.1, 4.3, 4.8_
     - [x] 11.2 Implement cancel, approve, reject, and markAsPaid methods
          - Validate status transitions
          - Store rejection reason
          - Record payment details
          - _Requirements: 4.4, 4.5, 4.6_
     - [x] 11.3 Write property test for reimbursement status workflow
          - **Property 15: Reimbursement status workflow**
          - **Validates: Requirements 4.2, 4.4, 4.5, 4.6**
     - [x] 11.4 Write property test for reimbursement employee scoping
          - **Property 16: Reimbursement employee scoping**
          - **Validates: Requirements 4.7**

- [x]    12. Checkpoint - Ensure all tests pass

     - Ensure all tests pass, ask the user if questions arise.

- [x]    13. Implement PayslipService

     - [x] 13.1 Create PayslipService with generate method
          - Generate HTML payslip with all components
          - Include gross income, BPJS breakdown, PPh 21, deductions, net income
          - _Requirements: 5.2, 5.3_
     - [x] 13.2 Implement generatePdf method with password protection
          - Use employee's last 6 digits of NIK or DOB as password
          - _Requirements: 5.3, 5.4_
     - [x] 13.3 Implement getEmployeePayslips method
          - Return payslips only for paid payrun batches
          - _Requirements: 5.1, 5.6_
     - [x] 13.4 Write property test for payslip availability after payment
          - **Property 17: Payslip availability after payment**
          - **Validates: Requirements 5.1**
     - [x] 13.5 Write property test for payslip content completeness
          - **Property 18: Payslip content completeness**
          - **Validates: Requirements 5.2**

- [x]    14. Create Payroll API endpoints

     - [x] 14.1 Create PayslipController with list and show endpoints
          - GET /api/hris/payslips - list employee's payslips
          - GET /api/hris/payslips/{id} - get payslip details with download URL
          - _Requirements: 7.1, 7.2_
     - [x] 14.2 Create ReimbursementController with CRUD endpoints
          - GET /api/hris/reimbursements - list employee's reimbursements
          - POST /api/hris/reimbursements - create with file upload
          - PUT /api/hris/reimbursements/{id}/cancel - cancel pending
          - _Requirements: 7.3, 7.4, 7.5_
     - [x] 14.3 Register payroll routes in routes/api.php
          - Add routes under /api/hris prefix
          - Apply auth:sanctum middleware
          - _Requirements: 7.1, 7.3_
     - [x] 14.4 Write property test for payslip employee authorization
          - **Property 19: Payslip employee authorization**
          - **Validates: Requirements 5.5**
     - [x] 14.5 Write API integration tests for payslip endpoints
          - Test list, show, and download endpoints
          - _Requirements: 7.1, 7.2_
     - [x] 14.6 Write API integration tests for reimbursement endpoints
          - Test create, list, and cancel endpoints
          - _Requirements: 7.3, 7.4, 7.5_

- [x]    15. Checkpoint - Ensure all tests pass

     - Ensure all tests pass, ask the user if questions arise.

- [x]    16. Create Filament resources for payroll administration

     - [x] 16.1 Create SalaryStructureResource
          - Form with employee select, effective_date, basic_salary, allowances repeater, deductions repeater, PPh method select, JKK risk level select
          - Table with employee, effective_date, basic_salary, PPh method
          - Add 75:25 rule validation
          - _Requirements: 6.1, 1.8_
     - [x] 16.2 Create PayrunBatchResource with workflow actions
          - Form with period dates and pay date
          - Table with period, pay_date, status, employee count
          - Add Calculate, Approve, Mark as Paid actions
          - _Requirements: 6.2, 6.3_
     - [x] 16.3 Create PayrollRecordResource as relation manager
          - Read-only table showing all payroll components
          - Nested under PayrunBatch view page
          - _Requirements: 6.3_
     - [x] 16.4 Create ReimbursementResource
          - Form with type, amount, description, receipt upload
          - Table with employee, type, amount, status
          - Highlight pending items
          - Add Approve, Reject, Mark as Paid actions
          - _Requirements: 6.4_
     - [x] 16.5 Add export action to PayrunBatchResource
          - Export payroll records to CSV/XLSX
          - Include bank transfer file generation
          - _Requirements: 6.5, 6.6_

- [x]    17. Create policies for payroll authorization

     - [x] 17.1 Create SalaryStructurePolicy
          - Restrict to hr-admin and finance roles
          - _Requirements: 8.1_
     - [x] 17.2 Create PayrunBatchPolicy
          - Create/calculate: hr-admin
          - Approve/pay: finance
          - _Requirements: 8.2_
     - [x] 17.3 Create PayrollRecordPolicy
          - View own: employee
          - View all: hr-admin, finance
          - _Requirements: 8.3_
     - [x] 17.4 Create ReimbursementPolicy
          - View own: employee
          - View pending: approvers
          - View all: hr-admin
          - _Requirements: 8.4_
     - [x] 17.5 Register policies in AuthServiceProvider
          - _Requirements: 8.1, 8.2, 8.3, 8.4_
     - [x] 17.6 Write property test for role-based payroll access
          - **Property 20: Role-based payroll access control**
          - **Validates: Requirements 8.1, 8.2**

- [x]    18. Add audit logging for payroll operations

     - [x] 18.1 Create PayrollAuditObserver for critical operations
          - Log salary structure changes
          - Log payrun batch status transitions
          - Log reimbursement approvals/rejections
          - _Requirements: 8.5_

- [x]    19. Final Checkpoint - Ensure all tests pass
     - Ensure all tests pass, ask the user if questions arise.
