# Requirements Document

## Introduction

This document specifies the requirements for Phase 3 of the HRIS module: Payroll & Payslips. This phase implements salary structure management, payroll processing (payrun batches), individual payroll record calculation, reimbursement claims, and payslip generation with ESS access. The system follows Indonesian HR and tax context including PPh 21 calculation and BPJS contributions.

## Glossary

- **Payroll_System**: The payroll processing module within the HRIS system
- **Salary_Structure**: Configuration defining an employee's compensation components (must follow 75:25 rule where basic salary is minimum 75% of basic + fixed allowances)
- **Fixed_Allowance**: Regular payment not tied to attendance (e.g., position allowance, family allowance)
- **Non_Fixed_Allowance**: Variable payment dependent on attendance or performance (e.g., transport, meal allowance)
- **JHT**: Jaminan Hari Tua (Old Age Security) - employee 2%, company 3.7%, no wage ceiling
- **JP**: Jaminan Pensiun (Pension) - employee 1%, company 2%, wage ceiling IDR 10,547,400
- **JKK**: Jaminan Kecelakaan Kerja (Work Accident) - company only 0.24-1.74% based on risk
- **JKM**: Jaminan Kematian (Death Insurance) - company only 0.30%
- **Payrun_Batch**: A payroll processing cycle for a specific period
- **Payroll_Record**: Individual employee's calculated payroll for a specific payrun
- **Reimbursement**: Employee expense claim for approved categories
- **Payslip**: Document showing employee's earnings and deductions for a pay period
- **PPh_21**: Indonesian income tax (Pajak Penghasilan Pasal 21)
- **BPJS_Kesehatan**: Indonesian national health insurance
- **BPJS_Ketenagakerjaan**: Indonesian workforce social security (JHT, JKK, JKM, JP)
- **TER**: Tarif Efektif Rata-rata (Effective Average Rate) for PPh 21 calculation
- **Gross_Income**: Total earnings before deductions
- **Net_Income**: Take-home pay after all deductions
- **Taxable_Income**: Income subject to PPh 21 calculation

## Requirements

### Requirement 1: Salary Structure Management

**User Story:** As an HR administrator, I want to configure employee salary structures, so that payroll calculations use accurate compensation data.

#### Acceptance Criteria

1. WHEN an HR administrator creates a salary structure THEN the Payroll_System SHALL store basic_salary, effective_date, and employee_id
2. WHEN configuring allowances THEN the Payroll_System SHALL support fixed allowances (transport, meal, position) and variable allowances stored as JSON
3. WHEN configuring deductions THEN the Payroll_System SHALL store deduction items as JSON with type and amount
4. WHEN setting PPh 21 method THEN the Payroll_System SHALL accept one of: netto, gross, or gross_up
5. WHEN configuring BPJS rates THEN the Payroll_System SHALL store separate employee and company contribution rates for both Kesehatan and Ketenagakerjaan
6. WHEN an employee has multiple salary structures THEN the Payroll_System SHALL use the structure with the most recent effective_date not exceeding the payrun period
7. WHEN retrieving salary structure THEN the Payroll_System SHALL return the active structure based on effective_date
8. WHEN validating salary structure THEN the Payroll_System SHALL enforce the 75:25 rule where basic_salary must be at least 75% of (basic_salary + total_fixed_allowances)
9. WHEN configuring JKK rate THEN the Payroll_System SHALL accept risk level (very_low, low, medium, high, very_high) mapping to rates 0.24%, 0.54%, 0.89%, 1.27%, 1.74%

### Requirement 2: Payrun Batch Management

**User Story:** As an HR/Finance administrator, I want to create and manage payroll processing batches, so that I can process payroll for all employees in a controlled workflow.

#### Acceptance Criteria

1. WHEN creating a payrun batch THEN the Payroll_System SHALL require period_start_date, period_end_date, and pay_date
2. WHEN a payrun batch is created THEN the Payroll_System SHALL set initial status to 'draft'
3. WHEN calculating a payrun batch THEN the Payroll_System SHALL process all active employees with valid salary structures
4. WHEN calculation completes THEN the Payroll_System SHALL update batch status to 'calculated'
5. WHEN approving a payrun batch THEN the Payroll_System SHALL validate all records are calculated and update status to 'approved'
6. WHEN marking a payrun batch as paid THEN the Payroll_System SHALL update status to 'paid' and record payment timestamp
7. IF a payrun batch status is 'approved' or 'paid' THEN the Payroll_System SHALL prevent modifications to payroll records
8. WHEN listing payrun batches THEN the Payroll_System SHALL display period, pay_date, status, and employee count

### Requirement 3: Payroll Record Calculation

**User Story:** As a payroll processor, I want the system to calculate individual employee payroll accurately, so that employees receive correct compensation.

#### Acceptance Criteria

1. WHEN calculating gross income THEN the Payroll_System SHALL sum basic_salary plus all fixed and variable allowances
2. WHEN calculating BPJS Kesehatan THEN the Payroll_System SHALL apply 4% company and 1% employee rates with maximum wage ceiling of IDR 12,000,000
3. WHEN calculating BPJS Ketenagakerjaan JHT THEN the Payroll_System SHALL apply 3.7% company and 2% employee rates with no wage ceiling
4. WHEN calculating BPJS Ketenagakerjaan JP THEN the Payroll_System SHALL apply 2% company and 1% employee rates with maximum wage ceiling of IDR 10,547,400
5. WHEN calculating BPJS Ketenagakerjaan JKK THEN the Payroll_System SHALL apply company-only rate between 0.24% and 1.74% based on work risk level
6. WHEN calculating BPJS Ketenagakerjaan JKM THEN the Payroll_System SHALL apply company-only rate of 0.30%
7. WHEN calculating taxable income THEN the Payroll_System SHALL compute gross_income minus employee BPJS contributions and applicable deductions
8. WHEN calculating PPh 21 with TER method THEN the Payroll_System SHALL apply the effective rate based on annual taxable income bracket
9. WHEN calculating net income THEN the Payroll_System SHALL compute gross_income minus PPh_21 minus employee_BPJS minus other_deductions
10. WHEN storing payroll record THEN the Payroll_System SHALL save detailed breakdown in JSON format including all components
11. WHEN recalculating a draft payrun THEN the Payroll_System SHALL update existing records rather than creating duplicates

### Requirement 4: Reimbursement Claims

**User Story:** As an employee, I want to submit expense reimbursement claims, so that I can be compensated for approved business expenses.

#### Acceptance Criteria

1. WHEN submitting a reimbursement THEN the Payroll_System SHALL require type, amount, description, and receipt file
2. WHEN a reimbursement is submitted THEN the Payroll_System SHALL set initial status to 'pending'
3. WHEN a reimbursement is submitted THEN the Payroll_System SHALL initiate the configured approval workflow
4. WHEN a reimbursement is approved THEN the Payroll_System SHALL update status to 'approved'
5. WHEN a reimbursement is rejected THEN the Payroll_System SHALL update status to 'rejected' and store rejection reason
6. WHEN a reimbursement is paid THEN the Payroll_System SHALL update status to 'paid' and record payment details
7. WHEN listing reimbursements THEN the Payroll_System SHALL filter by employee_id for ESS access
8. WHEN uploading receipt THEN the Payroll_System SHALL store file in private storage with secure access

### Requirement 5: Payslip Generation

**User Story:** As an employee, I want to view and download my payslips, so that I have records of my compensation.

#### Acceptance Criteria

1. WHEN a payrun batch is marked as paid THEN the Payroll_System SHALL make payslips available to employees
2. WHEN generating a payslip THEN the Payroll_System SHALL include gross income, all deductions, BPJS contributions, PPh 21, and net income
3. WHEN generating a payslip THEN the Payroll_System SHALL format as HTML with option to export as PDF
4. WHEN protecting a payslip PDF THEN the Payroll_System SHALL use employee's last 6 digits of NIK or date of birth as password
5. WHEN an employee requests payslip via API THEN the Payroll_System SHALL return payslip data only for the authenticated employee
6. WHEN listing payslips THEN the Payroll_System SHALL show all available periods with download status

### Requirement 6: Filament Admin Resources

**User Story:** As an HR/Finance administrator, I want to manage payroll through the admin panel, so that I can efficiently process and monitor payroll operations.

#### Acceptance Criteria

1. WHEN accessing SalaryStructureResource THEN the Payroll_System SHALL display employee, effective_date, basic_salary, and PPh method
2. WHEN accessing PayrunBatchResource THEN the Payroll_System SHALL provide actions for calculate, approve, mark_as_paid, and export
3. WHEN viewing a payrun batch THEN the Payroll_System SHALL display all payroll records with summary totals
4. WHEN accessing ReimbursementResource THEN the Payroll_System SHALL show pending items requiring approval prominently
5. WHEN exporting payroll data THEN the Payroll_System SHALL generate CSV/XLSX with all payroll record details
6. WHEN generating bank transfer file THEN the Payroll_System SHALL export employee bank details with net amounts

### Requirement 7: ESS API Endpoints

**User Story:** As an employee using the mobile app, I want to access my payroll information, so that I can view my compensation details on the go.

#### Acceptance Criteria

1. WHEN calling GET /api/hris/payslips THEN the Payroll_System SHALL return list of available payslips for authenticated employee
2. WHEN calling GET /api/hris/payslips/{id} THEN the Payroll_System SHALL return detailed payslip data with secure download URL
3. WHEN calling GET /api/hris/reimbursements THEN the Payroll_System SHALL return list of employee's reimbursement claims
4. WHEN calling POST /api/hris/reimbursements THEN the Payroll_System SHALL create new reimbursement claim with file upload support
5. WHEN calling PUT /api/hris/reimbursements/{id}/cancel THEN the Payroll_System SHALL cancel pending reimbursement if owned by employee

### Requirement 8: Authorization and Security

**User Story:** As a system administrator, I want payroll data to be properly secured, so that sensitive compensation information is protected.

#### Acceptance Criteria

1. WHEN accessing salary structures THEN the Payroll_System SHALL restrict to hr-admin and finance roles
2. WHEN accessing payrun batches THEN the Payroll_System SHALL restrict create/calculate to hr-admin, approve/pay to finance
3. WHEN an employee accesses payslips THEN the Payroll_System SHALL return only their own payslip data
4. WHEN accessing reimbursements THEN the Payroll_System SHALL allow employees to view own, approvers to view pending, hr-admin to view all
5. WHEN logging payroll operations THEN the Payroll_System SHALL record user, action, and timestamp for audit trail
