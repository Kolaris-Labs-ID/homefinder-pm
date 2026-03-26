# Test Failures Analysis and Fixes

## Summary

18 tests failed due to several interconnected issues in the payroll calculation system:

1. **PPh21 Tax Calculation** - Incorrect tax rate for 20M income bracket
2. **Gross Income Calculation** - Not being calculated/stored properly (returns 0)
3. **Net Income Calculation** - Becomes negative due to missing gross income
4. **Payslip API** - Returns 0 for earnings because gross income is 0
5. **Photo Service** - Missing Supabase configuration in test environment

## Failed Tests Breakdown

### 1. PPh21 Tax Calculation (1 failure)

**Test:** `Pph21CalculationServiceTest > calculates correct tax for higher income`
**Issue:** Expected 1,600,000 but got 1,800,000 for 20M income
**Root Cause:** The TER rate for 20M income is returning 9% instead of 8%

### 2. Gross Income Calculation (2 failures)

**Tests:**

- `PayrollGrossIncomePropertyTest > Property 8: Gross income calculation accuracy`
- `PayrollGrossIncomePropertyTest > Property 8 Edge Cases`

**Issue:** `gross_income` field returns 0.00 instead of calculated value
**Root Cause:** The `calculateEmployee` method calculates `$grossIncome` but the value isn't being persisted correctly to the database

### 3. Net Income Calculation (2 failures)

**Tests:**

- `PayrollNetIncomePropertyTest > Property 13 Edge Cases`
- `PayrollCalculationServiceTest > full calculation`

**Issue:** Net income is negative or equal to gross income
**Root Cause:** Since gross income is 0, deductions make net income negative

### 4. Payslip API (2 failures)

**Tests:**

- `PayslipTest > returns correct earnings breakdown`
- `PayslipTest > returns correct net income calculation`

**Issue:** API returns 0 for all earnings fields
**Root Cause:** Payroll records have 0 gross income, cascading from issue #2

### 5. Payrun Calculation (2 failures)

**Tests:**

- `PayrunCalculationFilteringPropertyTest > Property 7 Edge Cases`
- `PayrunRecalculationIdempotencyPropertyTest > Property 14 Edge Cases`

**Issue:** Basic salary returns 0 after calculation
**Root Cause:** Same as gross income issue - calculation happens but values aren't stored

### 6. Payslip Content (3 failures)

**Tests:**

- `PayslipContentCompletenessPropertyTest > Property 18`
- `PayslipContentCompletenessPropertyTest > Property 18 Edge Cases`
- `PayslipContentCompletenessPropertyTest > Property 18 Additional`

**Issue:** Generated HTML doesn't contain expected Indonesian text labels
**Root Cause:** Payslip template missing or not rendering properly

### 7. Photo Service (6 failures)

**Tests:** All PhotoServicePropertyTest tests
**Issue:** "Supabase storage is not configured" exception
**Root Cause:** Tests expect local storage fallback but service throws exception when Supabase isn't configured

## Root Causes

### Primary Issue: Gross Income Not Being Stored

The `PayrollCalculationService::calculateEmployee()` method calculates `$grossIncome` correctly:

```php
$grossIncome = $proratedBasicSalary + $totalFixedAllowances + $totalVariableAllowances + $dailyAllowanceTotal;
```

But when creating/updating the PayrollRecord, the value might not be persisting. This could be due to:

1. Database column type mismatch
2. Model accessor/mutator interfering
3. Validation failing silently
4. Transaction rollback

### Secondary Issue: PPh21 Bracket Configuration

The TER brackets in `PayrollSettingService` need to be checked. The 20M income should fall in the 8% bracket, not 9%.

### Tertiary Issue: Photo Service Exception Handling

The `PhotoService` should gracefully fall back to local storage when Supabase isn't configured, not throw an exception.

## Fixes Required

### Fix 1: Check PayrollRecord Model

Examine the `PayrollRecord` model for:

- Fillable fields include `gross_income`
- No mutators that might be setting it to 0
- Casts are correct (decimal/float)

### Fix 2: Check Database Migration

Verify the `payroll_records` table has:

- `gross_income` column with correct type (decimal(15,2))
- No default value of 0 that overrides

### Fix 3: Fix PPh21 Brackets

Update `PayrollSettingService::getPph21Brackets()` to ensure:

- 20M falls in 8% bracket (15M - 20M range)
- Not in 9% bracket

### Fix 4: Fix PhotoService

Update `PhotoService::processAttendancePhoto()` to:

- Check if Supabase is configured
- If not, use local storage without throwing exception
- Only throw exception if both Supabase AND local storage fail

### Fix 5: Check Payslip Template

Verify the payslip Blade template includes:

- "PENDAPATAN" / "EARNINGS" labels
- "POTONGAN" / "DEDUCTIONS" labels
- All required Indonesian translations

## Testing Strategy

1. **Unit Test First:** Fix PPh21 calculation and verify with unit test
2. **Integration Test:** Fix gross income storage and verify with property tests
3. **API Test:** Verify payslip API returns correct values
4. **Service Test:** Fix PhotoService and verify all photo tests pass

## Priority Order

1. **HIGH:** Fix gross income calculation/storage (affects 11 tests)
2. **MEDIUM:** Fix PPh21 brackets (affects 1 test)
3. **MEDIUM:** Fix PhotoService (affects 6 tests)
4. **LOW:** Fix payslip template (cosmetic, tests may be too strict)
