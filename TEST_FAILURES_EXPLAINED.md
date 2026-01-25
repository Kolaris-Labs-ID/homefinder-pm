# Test Failures - Detailed Explanation

## Overview

18 tests failed across 7 test suites. After analyzing the code, I've identified the root causes:

## Issues Found

### 1. PPh21 Tax Calculation (1 failure)

**Test:** `Pph21CalculationServiceTest > calculates correct tax for higher income`

**Expected:** 1,600,000 (20M × 8%)
**Got:** 1,800,000 (20M × 9%)

**Root Cause:** The PPh21 TER brackets in `PayrollSettingService.php` line 18 shows:

```php
['min' => 16950000, 'max' => 19750000, 'rate' => 0.08],  // 8% bracket ends at 19.75M
['min' => 19750000, 'max' => 24150000, 'rate' => 0.09],  // 9% bracket starts at 19.75M
```

20M falls in the 9% bracket (19.75M - 24.15M), not the 8% bracket.

**Fix:** The test expectation is wrong. 20M should be taxed at 9%, not 8%. The calculation is correct.

### 2. Gross Income Returns Zero (11 failures)

**Affected Tests:**

- PayrollGrossIncomePropertyTest (2 tests)
- PayrollNetIncomePropertyTest (2 tests)
- PayslipTest (2 tests)
- PayrollCalculationServiceTest (1 test)
- PayrunCalculationFilteringPropertyTest (2 tests)
- PayrunRecalculationIdempotencyPropertyTest (2 tests)

**Issue:** All payroll calculations return `gross_income = 0.00`

**Root Cause Analysis:**
Looking at `PayrollCalculationService.php` line 68-70:

```php
$grossIncome = $proratedBasicSalary + $totalFixedAllowances + $totalVariableAllowances + $dailyAllowanceTotal;
```

The calculation happens correctly, BUT the issue is in the test setup. The tests are NOT creating attendance records, so:

- `$actualDaysWorked = 0` (no attendance)
- `$prorateRatio = 0/21 = 0` (when prorate is enabled)
- `$proratedBasicSalary = basicSalary × 0 = 0`
- `$totalFixedAllowances = allowances × 0 = 0`
- `$dailyAllowanceTotal = rate × 0 days = 0`
- Therefore: `$grossIncome = 0 + 0 + 0 + 0 = 0`

**Why This Happens:**
The tests create employees and salary structures but DON'T create attendance logs. When prorate is enabled (which it is by default in most tests), the system calculates based on actual days worked. With zero attendance, everything becomes zero.

**Fix:** Tests need to either:

1. Create attendance records for the payroll period, OR
2. Set `prorate_enabled = false` in the salary structure

### 3. Payslip Content Missing Labels (3 failures)

**Tests:** PayslipContentCompletenessPropertyTest (3 tests)

**Issue:** Generated HTML doesn't contain "PENDAPATAN" text

**Root Cause:** The payslip generation service likely exists but the tests are checking for specific Indonesian labels that may not be in the template, or the template isn't being rendered.

**Fix:** Need to check the payslip Blade template or the PayslipService to ensure it includes bilingual labels.

### 4. PhotoService Supabase Exception (6 failures)

**Tests:** All PhotoServicePropertyTest tests

**Issue:** Tests throw "Supabase storage is not configured" exception

**Root Cause:** Looking at `PhotoService.php` line 65:

```php
if (!$this->isSupabaseConfigured()) {
    throw new \Exception('Supabase storage is not configured...');
}
```

The service throws an exception instead of falling back to local storage. The tests expect local storage fallback but the service doesn't implement it.

**Fix:** The PhotoService needs to support local storage fallback for testing, OR the tests need to mock Supabase configuration.

## Summary of Fixes Needed

### Priority 1: Fix Gross Income (11 tests)

**Solution:** Update all affected tests to create attendance records:

```php
// Add this to test setup
for ($i = 0; $i < 21; $i++) {  // 21 working days
    $date = $batch->period_start_date->copy()->addDays($i);
    if (!$date->isWeekend()) {
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'date' => $date,
            'status' => AttendanceLog::STATUS_PRESENT,
            'mode' => AttendanceLog::MODE_OFFICE,
            'source' => AttendanceLog::SOURCE_WEB,
        ]);
    }
}
```

OR disable prorate in tests:

```php
SalaryStructure::factory()->create([
    'employee_id' => $employee->id,
    'prorate_enabled' => false,  // Add this
    // ... other fields
]);
```

### Priority 2: Fix PhotoService (6 tests)

**Solution:** Add local storage fallback to PhotoService OR mock Supabase in tests.

### Priority 3: Fix PPh21 Test (1 test)

**Solution:** Update test expectation from 1,600,000 to 1,800,000.

### Priority 4: Fix Payslip Labels (3 tests)

**Solution:** Check PayslipService and ensure bilingual labels are included.

## Recommended Approach

1. **Disable prorate in all test factories** - Simplest fix
2. **Add local storage fallback to PhotoService** - Better for testing
3. **Fix PPh21 test expectation** - One line change
4. **Verify payslip template** - May need template update
