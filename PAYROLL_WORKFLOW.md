# Payroll Workflow Documentation

## Overview

The payroll system handles two disbursement cycles:

1. **Monthly Payroll** — full salary calculation per period via `PayrunBatch`
2. **Weekly Allowance (Uang Harian)** — weekly disbursement of daily allowances via `WeeklyAllowanceBatch`

Both flows are managed through the Filament admin panel and backed by a layered service architecture.

---

## Architecture

```
Admin Panel (Filament)
    └── PayrunBatchResource / WeeklyAllowanceBatchResource
            │
            ▼
    PayrollCalculationService / WeeklyAllowanceService
            │
            ├── AttendancePayrollService   (attendance & prorate)
            ├── BpjsCalculationService     (BPJS contributions)
            ├── Pph21CalculationService    (income tax / PPh 21)
            └── PayrollSettingService      (configurable rates)
            │
            ▼
    PayrollRecord / WeeklyAllowancePayment  (persisted results)
```

---

## 1. Monthly Payroll Flow

### 1.1 Status Lifecycle

```
draft → calculated → approved → paid
```

| Status       | Description                              |
| ------------ | ---------------------------------------- |
| `draft`      | Batch created, not yet calculated        |
| `calculated` | All employee records computed            |
| `approved`   | Reviewed and approved by authorized user |
| `paid`       | Disbursed; batch and records are locked  |

### 1.2 Step-by-Step Process

#### Step 1 — Create PayrunBatch (Draft)

Admin creates a batch with:

- `period_start_date` / `period_end_date` — payroll period
- `pay_date` — scheduled disbursement date

#### Step 2 — Calculate Payrun

Triggers `PayrollCalculationService::calculatePayrun()` which iterates all active employees and calls `calculateEmployee()` for each.

**Per-employee calculation order:**

```
1. Resolve active SalaryStructure (most recent effective_date ≤ period_end)
2. Get attendance summary (AttendancePayrollService)
3. Calculate prorate ratio (if prorate_enabled)
4. Calculate prorated basic salary
5. Calculate fixed allowances (prorated if enabled)
6. Calculate variable allowances (attendance-based)
7. Calculate daily allowance total (daily_allowance_rate × actual_days_worked)
8. Weekly allowance reconciliation (if weekly_allowance_enabled)
9. Sum gross income
10. Calculate BPJS (on full salary base, not prorated)
11. Calculate taxable income (gross − employee BPJS)
12. Calculate PPh 21 (TER method)
13. Calculate other deductions (prorated if enabled)
14. Sum total deductions
15. Net income = gross − total deductions
16. Persist to PayrollRecord (upsert)
```

Batch status → `calculated`.

#### Step 3 — Approve

`PayrollCalculationService::approve()` — sets status to `approved`, records `approved_by` and `approved_at`.

#### Step 4 — Mark as Paid

`PayrollCalculationService::markAsPaid()` — sets status to `paid`, records `paid_by` and `paid_at`. Batch and all records become **locked** (no further edits).

#### Step 5 — Recalculate (optional)

Only allowed while status is `draft` or `calculated`. Resets to `draft` then re-runs calculation. Idempotent.

---

## 2. Salary Calculation Details

### 2.1 Prorate

When `SalaryStructure.prorate_enabled = true`:

```
prorate_ratio = actual_days_worked / total_working_days   (capped at 1.0)
prorated_basic_salary = full_basic_salary × prorate_ratio
```

`actual_days_worked` = `present_days` + `late_days` + (`half_days` × 0.5)

`total_working_days` = weekdays (Mon–Fri) in the period, excluding weekends.

### 2.2 Allowances

| Type                | Calculation                                      |
| ------------------- | ------------------------------------------------ |
| Fixed allowances    | Sum of `allowances_fixed[]`, prorated if enabled |
| Variable allowances | Attendance-based, configured per allowance item  |
| Daily allowance     | `daily_allowance_rate × actual_days_worked`      |

### 2.3 BPJS Contributions

BPJS is calculated on **full salary base** (not prorated):

```
bpjs_base = full_basic_salary + total_fixed_allowances
```

| Component | Employee | Company              | Ceiling       |
| --------- | -------- | -------------------- | ------------- |
| Kesehatan | 1%       | 4%                   | Rp 12,000,000 |
| JHT       | 2%       | 3.7%                 | No ceiling    |
| JP        | 1%       | 2%                   | Rp 10,547,400 |
| JKK       | —        | 0.24–1.74% (by risk) | —             |
| JKM       | —        | 0.3%                 | —             |

> All rates are configurable via `PayrollSetting` (not hardcoded).

**BPJS paid by company** (`bpjs_paid_by = 'company'`): employee contributions become Rp 0; company absorbs the full amount.

### 2.4 PPh 21 (Income Tax)

Calculated using the **TER (Tarif Efektif Rata-rata)** method:

```
taxable_income = gross_income − employee_bpjs_total
pph21 = taxable_income × TER_rate
```

TER brackets are configurable via `PayrollSetting`. Three methods supported:

| Method     | Description                                         |
| ---------- | --------------------------------------------------- |
| `gross`    | Tax borne by employee, calculated on gross          |
| `netto`    | Same as gross (employee bears tax)                  |
| `gross_up` | Tax borne by company: `(gross × rate) / (1 − rate)` |

### 2.5 Net Income

```
gross_income = prorated_basic + fixed_allowances + variable_allowances + daily_allowance
total_deductions = employee_bpjs + pph21 + other_deductions
net_income = gross_income − total_deductions
```

---

## 3. Weekly Allowance Flow

### 3.1 Status Lifecycle

```
draft → pending_hr → pending_finance → pending_final → approved → paid
                                                              └── cancelled (any pre-paid stage)
```

### 3.2 Step-by-Step Process

#### Step 1 — Generate Batch

`WeeklyAllowanceService::generateBatch(weekStart, weekEnd, paymentDate)`

- Covers Mon–Fri of the previous week
- Payment date is typically the following Tuesday
- Queries all employees with `weekly_allowance_enabled = true` on their salary structure

#### Step 2 — Calculate Per-Employee Payment

For each eligible employee:

```
1. Resolve active SalaryStructure
2. Get daily_allowance_rate
3. Get attendance for Mon–Fri (present / late / WFH = counted)
4. days_present = count of qualifying days
5. total_amount = daily_allowance_rate × days_present
6. Snapshot bank info (bank_name, account_number, account_holder)
7. Flag has_bank_warning if any bank field is missing
```

#### Step 3 — HR Review (`pending_hr`)

HR reviews payments, can edit individual amounts. Cannot submit to Finance if any `has_bank_warning = true`.

#### Step 4 — Finance Approval (`pending_finance → pending_final`)

Finance approves; all payments move to `approved` status.

#### Step 5 — Final Approval (`pending_final → approved`)

COO (or configured final approver) gives final sign-off.

#### Step 6 — Mark as Paid

All payments move to `paid`. Batch is locked.

#### Step 7 — Export for Bank Transfer

CSV export generated with: Bank, Account Number, Account Name, Amount, Description.

---

## 4. Monthly Payroll Reconciliation

When `weekly_allowance_enabled = true` on a salary structure, the monthly payroll reconciles weekly payments:

```
weekly_allowance_earned   = daily_allowance_rate × actual_days_worked (monthly)
weekly_allowance_paid     = sum of paid WeeklyAllowancePayments in the period
weekly_allowance_adjustment = earned − paid
```

The adjustment appears on the payslip to show the net difference between what was earned and what was already disbursed weekly.

---

## 5. Key Models

| Model                    | Purpose                                           |
| ------------------------ | ------------------------------------------------- |
| `PayrunBatch`            | Monthly payroll batch container                   |
| `PayrollRecord`          | Per-employee payroll result for a batch           |
| `SalaryStructure`        | Employee salary config (rates, allowances, flags) |
| `PayrollSetting`         | Configurable BPJS/tax rates stored in DB          |
| `WeeklyAllowanceBatch`   | Weekly allowance batch container                  |
| `WeeklyAllowancePayment` | Per-employee weekly allowance payment             |
| `AttendanceLog`          | Daily attendance records used for calculation     |

---

## 6. Key Services

| Service                     | Responsibility                                       |
| --------------------------- | ---------------------------------------------------- |
| `PayrollCalculationService` | Orchestrates full monthly payroll calculation        |
| `AttendancePayrollService`  | Attendance summary, prorate ratio, daily allowance   |
| `BpjsCalculationService`    | BPJS component calculations                          |
| `Pph21CalculationService`   | PPh 21 / TER tax calculation                         |
| `PayrollSettingService`     | Retrieves configurable rates from `payroll_settings` |
| `WeeklyAllowanceService`    | Weekly allowance batch generation and workflow       |

---

## 7. SalaryStructure Configuration Flags

| Field                      | Type    | Effect                                               |
| -------------------------- | ------- | ---------------------------------------------------- |
| `prorate_enabled`          | boolean | Enables prorate calculation based on attendance      |
| `daily_allowance_rate`     | decimal | Per-day allowance amount (Uang Harian)               |
| `bpjs_paid_by`             | enum    | `employee` / `company` / `shared`                    |
| `pph21_method`             | enum    | `gross` / `netto` / `gross_up`                       |
| `jkk_risk_level`           | enum    | `very_low` to `very_high` — affects JKK rate         |
| `weekly_allowance_enabled` | boolean | Enables weekly disbursement + monthly reconciliation |

---

## 8. Payslip Output (PENGHASILAN / PENGURANGAN)

**PENGHASILAN (Income)**

- Gaji Pokok (full)
- Prorata Hari Kerja (if prorated)
- Tunjangan Tetap (fixed allowances)
- Tunjangan Variabel (variable allowances)
- Uang Harian (daily allowance)
- BPJS ditanggung kantor (if company-paid)

**PENGURANGAN (Deductions)**

- BPJS Kesehatan (employee share)
- BPJS JHT (employee share)
- BPJS JP (employee share)
- PPh 21
- Other deductions

**TAKE HOME PAY = Gross − Total Deductions**
