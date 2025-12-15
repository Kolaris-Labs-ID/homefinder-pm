# Design Document

## Overview

This design document describes the architecture and implementation for HRIS Phase 3: Payroll & Payslips. The system provides comprehensive payroll processing including salary structure management, batch payroll calculation with Indonesian tax (PPh 21) and BPJS compliance, reimbursement claims with approval workflows, and payslip generation for employee self-service access.

## Architecture

The payroll module follows the existing HRIS architecture pattern:

```
┌─────────────────────────────────────────────────────────────────┐
│                      Filament Admin Panel                        │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐   │
│  │SalaryStructure  │ │PayrunBatch      │ │Reimbursement    │   │
│  │Resource         │ │Resource         │ │Resource         │   │
│  └────────┬────────┘ └────────┬────────┘ └────────┬────────┘   │
└───────────┼────────────────────┼────────────────────┼───────────┘
            │                    │                    │
┌───────────┼────────────────────┼────────────────────┼───────────┐
│           ▼                    ▼                    ▼           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    Service Layer                         │   │
│  │  ┌─────────────┐ ┌─────────────┐ ┌─────────────────┐    │   │
│  │  │PayrollCalc  │ │PayslipGen   │ │Reimbursement    │    │   │
│  │  │Service      │ │Service      │ │Service          │    │   │
│  │  └─────────────┘ └─────────────┘ └─────────────────┘    │   │
│  │  ┌─────────────┐ ┌─────────────┐                        │   │
│  │  │BpjsCalc     │ │Pph21Calc    │                        │   │
│  │  │Service      │ │Service      │                        │   │
│  │  └─────────────┘ └─────────────┘                        │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
            │                    │                    │
┌───────────┼────────────────────┼────────────────────┼───────────┐
│           ▼                    ▼                    ▼           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    Model Layer                           │   │
│  │  SalaryStructure  PayrunBatch  PayrollRecord            │   │
│  │  Reimbursement    (uses existing Employee, User)        │   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
            │
┌───────────┼─────────────────────────────────────────────────────┐
│           ▼                                                      │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │                    API Layer (ESS)                       │   │
│  │  GET /payslips  GET /payslips/{id}  POST /reimbursements│   │
│  └─────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### Models

#### SalaryStructure

- `id`, `employee_id`, `effective_date`
- `basic_salary` (decimal)
- `allowances_fixed` (JSON: [{type, name, amount}])
- `allowances_variable` (JSON: [{type, name, amount}])
- `deductions` (JSON: [{type, name, amount}])
- `pph21_method` (enum: netto, gross, gross_up)
- `jkk_risk_level` (enum: very_low, low, medium, high, very_high)
- Relationships: belongsTo Employee

#### PayrunBatch

- `id`, `period_start_date`, `period_end_date`, `pay_date`
- `status` (enum: draft, calculated, approved, paid)
- `notes`, `calculated_at`, `approved_at`, `paid_at`
- `approved_by`, `paid_by` (FK to users)
- Relationships: hasMany PayrollRecord

#### PayrollRecord

- `id`, `payrun_batch_id`, `employee_id`
- `basic_salary`, `total_fixed_allowances`, `total_variable_allowances`
- `gross_income`, `taxable_income`
- `bpjs_kesehatan_employee`, `bpjs_kesehatan_company`
- `bpjs_jht_employee`, `bpjs_jht_company`
- `bpjs_jp_employee`, `bpjs_jp_company`
- `bpjs_jkk_company`, `bpjs_jkm_company`
- `pph21_amount`, `total_deductions`, `net_income`
- `details` (JSON: full breakdown)
- Relationships: belongsTo PayrunBatch, belongsTo Employee

#### Reimbursement

- `id`, `employee_id`, `type` (enum: medical, glasses, transport, other)
- `amount`, `description`, `receipt_file_path`
- `status` (enum: pending, approved, rejected, paid)
- `paid_at`, `paid_by`, `rejection_reason`
- `metadata` (JSON)
- Relationships: belongsTo Employee, morphMany ApprovalStep

### Services

#### PayrollCalculationService

```php
class PayrollCalculationService
{
    public function calculatePayrun(PayrunBatch $batch): void;
    public function calculateEmployee(Employee $employee, PayrunBatch $batch): PayrollRecord;
    public function getActiveSalaryStructure(Employee $employee, Carbon $date): ?SalaryStructure;
}
```

#### BpjsCalculationService

```php
class BpjsCalculationService
{
    const KESEHATAN_CEILING = 12000000;
    const JP_CEILING = 10547400;
    const JKK_RATES = ['very_low' => 0.0024, 'low' => 0.0054, ...];

    public function calculateKesehatan(float $salary): array; // [employee, company]
    public function calculateJht(float $salary): array;
    public function calculateJp(float $salary): array;
    public function calculateJkk(float $salary, string $riskLevel): float;
    public function calculateJkm(float $salary): float;
    public function calculateAll(float $salary, string $jkkRiskLevel): array;
}
```

#### Pph21CalculationService

```php
class Pph21CalculationService
{
    public function calculateTer(float $annualTaxableIncome): float;
    public function calculateMonthlyTax(float $taxableIncome, string $method): float;
}
```

#### PayslipService

```php
class PayslipService
{
    public function generate(PayrollRecord $record): string; // HTML
    public function generatePdf(PayrollRecord $record, ?string $password = null): string; // file path
    public function getEmployeePayslips(Employee $employee): Collection;
}
```

#### ReimbursementService

```php
class ReimbursementService
{
    public function create(Employee $employee, array $data, ?UploadedFile $receipt): Reimbursement;
    public function cancel(Reimbursement $reimbursement): void;
    public function markAsPaid(Reimbursement $reimbursement, User $paidBy): void;
}
```

## Data Models

### Database Schema

```sql
-- salary_structures
CREATE TABLE salary_structures (
    id BIGINT PRIMARY KEY,
    employee_id BIGINT REFERENCES employees(id),
    effective_date DATE NOT NULL,
    basic_salary DECIMAL(15,2) NOT NULL,
    allowances_fixed JSON,
    allowances_variable JSON,
    deductions JSON,
    pph21_method ENUM('netto', 'gross', 'gross_up') DEFAULT 'gross',
    jkk_risk_level ENUM('very_low', 'low', 'medium', 'high', 'very_high') DEFAULT 'low',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (employee_id, effective_date)
);

-- payrun_batches
CREATE TABLE payrun_batches (
    id BIGINT PRIMARY KEY,
    period_start_date DATE NOT NULL,
    period_end_date DATE NOT NULL,
    pay_date DATE NOT NULL,
    status ENUM('draft', 'calculated', 'approved', 'paid') DEFAULT 'draft',
    notes TEXT,
    calculated_at TIMESTAMP,
    approved_at TIMESTAMP,
    approved_by BIGINT REFERENCES users(id),
    paid_at TIMESTAMP,
    paid_by BIGINT REFERENCES users(id),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (status),
    INDEX (period_start_date, period_end_date)
);

-- payroll_records
CREATE TABLE payroll_records (
    id BIGINT PRIMARY KEY,
    payrun_batch_id BIGINT REFERENCES payrun_batches(id),
    employee_id BIGINT REFERENCES employees(id),
    basic_salary DECIMAL(15,2),
    total_fixed_allowances DECIMAL(15,2),
    total_variable_allowances DECIMAL(15,2),
    gross_income DECIMAL(15,2),
    taxable_income DECIMAL(15,2),
    bpjs_kesehatan_employee DECIMAL(15,2),
    bpjs_kesehatan_company DECIMAL(15,2),
    bpjs_jht_employee DECIMAL(15,2),
    bpjs_jht_company DECIMAL(15,2),
    bpjs_jp_employee DECIMAL(15,2),
    bpjs_jp_company DECIMAL(15,2),
    bpjs_jkk_company DECIMAL(15,2),
    bpjs_jkm_company DECIMAL(15,2),
    pph21_amount DECIMAL(15,2),
    total_deductions DECIMAL(15,2),
    net_income DECIMAL(15,2),
    details JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE (payrun_batch_id, employee_id),
    INDEX (employee_id)
);

-- reimbursements
CREATE TABLE reimbursements (
    id BIGINT PRIMARY KEY,
    employee_id BIGINT REFERENCES employees(id),
    type ENUM('medical', 'glasses', 'transport', 'other') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    receipt_file_path VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
    rejection_reason TEXT,
    paid_at TIMESTAMP,
    paid_by BIGINT REFERENCES users(id),
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX (employee_id),
    INDEX (status)
);
```

## Correctness Properties

_A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees._

### Property 1: Salary structure data persistence round-trip

_For any_ valid salary structure with basic_salary, allowances (fixed/variable), deductions, and PPh method, storing and retrieving the structure SHALL return equivalent data.
**Validates: Requirements 1.1, 1.2, 1.3, 1.4**

### Property 2: Active salary structure selection

_For any_ employee with multiple salary structures and a given payrun date, the system SHALL return the structure with the most recent effective_date that does not exceed the payrun date.
**Validates: Requirements 1.6, 1.7**

### Property 3: Salary structure 75:25 rule validation

_For any_ salary structure where basic_salary is less than 75% of (basic_salary + total_fixed_allowances), the system SHALL reject the structure with a validation error.
**Validates: Requirements 1.8**

### Property 4: JKK risk level to rate mapping

_For any_ JKK risk level (very_low, low, medium, high, very_high), the system SHALL return the correct rate (0.24%, 0.54%, 0.89%, 1.27%, 1.74% respectively).
**Validates: Requirements 1.9**

### Property 5: Payrun batch status workflow

_For any_ payrun batch, the status SHALL transition only in the order: draft → calculated → approved → paid, and each transition SHALL record the appropriate timestamp.
**Validates: Requirements 2.2, 2.4, 2.5, 2.6**

### Property 6: Payrun batch immutability after approval

_For any_ payrun batch with status 'approved' or 'paid', attempts to modify payroll records SHALL be rejected.
**Validates: Requirements 2.7**

### Property 7: Payrun calculation processes only valid employees

_For any_ payrun batch calculation, the system SHALL create payroll records only for active employees who have a valid salary structure for the payrun period.
**Validates: Requirements 2.3**

### Property 8: Gross income calculation accuracy

_For any_ payroll record, gross_income SHALL equal basic_salary + sum(fixed_allowances) + sum(variable_allowances).
**Validates: Requirements 3.1**

### Property 9: BPJS Kesehatan calculation with ceiling

_For any_ salary, BPJS Kesehatan employee contribution SHALL be 1% of min(salary, 12,000,000) and company contribution SHALL be 4% of min(salary, 12,000,000).
**Validates: Requirements 3.2**

### Property 10: BPJS JHT calculation without ceiling

_For any_ salary, BPJS JHT employee contribution SHALL be 2% of salary and company contribution SHALL be 3.7% of salary.
**Validates: Requirements 3.3**

### Property 11: BPJS JP calculation with ceiling

_For any_ salary, BPJS JP employee contribution SHALL be 1% of min(salary, 10,547,400) and company contribution SHALL be 2% of min(salary, 10,547,400).
**Validates: Requirements 3.4**

### Property 12: BPJS JKK and JKM calculation

_For any_ salary and JKK risk level, JKK contribution SHALL be the risk-level rate applied to salary (company only), and JKM SHALL be 0.30% of salary (company only).
**Validates: Requirements 3.5, 3.6**

### Property 13: Net income calculation formula

_For any_ payroll record, net_income SHALL equal gross_income - pph21_amount - employee_bpjs_total - other_deductions.
**Validates: Requirements 3.9**

### Property 14: Payrun recalculation idempotency

_For any_ draft payrun batch, calculating twice SHALL result in the same number of payroll records (no duplicates) with updated values.
**Validates: Requirements 3.11**

### Property 15: Reimbursement status workflow

_For any_ reimbursement, the status SHALL transition only in valid paths: pending → approved → paid, or pending → rejected, and rejection SHALL store the reason.
**Validates: Requirements 4.2, 4.4, 4.5, 4.6**

### Property 16: Reimbursement employee scoping

_For any_ employee querying reimbursements, the system SHALL return only reimbursements belonging to that employee.
**Validates: Requirements 4.7**

### Property 17: Payslip availability after payment

_For any_ payrun batch marked as paid, all associated payroll records SHALL be accessible as payslips to their respective employees.
**Validates: Requirements 5.1**

### Property 18: Payslip content completeness

_For any_ generated payslip, the output SHALL contain gross_income, all BPJS components, pph21_amount, total_deductions, and net_income.
**Validates: Requirements 5.2**

### Property 19: Payslip employee authorization

_For any_ payslip request via API, the system SHALL return data only if the authenticated employee owns the payroll record.
**Validates: Requirements 5.5**

### Property 20: Role-based payroll access control

_For any_ user without hr-admin or finance role, access to salary structures and payrun batches SHALL be denied.
**Validates: Requirements 8.1, 8.2**

## Error Handling

### Validation Errors

- Invalid salary structure (75:25 rule violation): Return 422 with specific error message
- Missing required fields: Return 422 with field-specific errors
- Invalid status transitions: Return 400 with current status and allowed transitions
- Unauthorized access: Return 403 with permission denied message

### Calculation Errors

- Missing salary structure: Skip employee in payrun, log warning
- Invalid BPJS rates: Use default rates, log warning
- Division by zero in calculations: Return 0, log error

### File Handling Errors

- Receipt upload failure: Return 500, rollback reimbursement creation
- PDF generation failure: Return 500, provide HTML fallback

## Testing Strategy

### Property-Based Testing Library

- **Pest PHP** with custom generators for Indonesian payroll data
- Minimum 100 iterations per property test

### Unit Tests

- BpjsCalculationService: Test each BPJS component calculation
- Pph21CalculationService: Test TER rate brackets
- PayrollCalculationService: Test gross/net income formulas

### Property-Based Tests

Each correctness property will be implemented as a property-based test with:

- Random salary amounts (IDR 1,000,000 - 100,000,000)
- Random allowance configurations
- Random employee counts for batch tests
- Edge cases: ceiling boundaries, zero values

### Integration Tests

- API endpoints for payslips and reimbursements
- Filament resource CRUD operations
- Approval workflow integration

### Test Annotations

Each property-based test MUST include:

```php
/**
 * Feature: hris-phase3-payroll, Property {number}: {property_text}
 * Validates: Requirements X.Y
 */
```
