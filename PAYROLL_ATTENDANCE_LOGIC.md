# Payroll Attendance Logic Documentation

## Overview

The payroll attendance system uses a **26th-to-25th payroll period** logic. This means attendance records from the 26th of one month through the 25th of the next month are grouped together in a single Google Sheet named after the **ending month**.

## Payroll Period Structure

### Period Definition
- **Start Date**: 26th of the month
- **End Date**: 25th of the next month
- **Sheet Name**: Named after the **ending month**

### Examples

| Date Range | Sheet Name | Explanation |
|------------|------------|-------------|
| January 1-25, 2025 | `January 2025` | Before cutoff, stays in current month |
| January 26-31, 2025 | `February 2025` | After cutoff, rolls into next month |
| February 1-25, 2025 | `February 2025` | Before cutoff, stays in current month |
| February 26-28, 2025 | `March 2025` | After cutoff, rolls into next month |
| December 26-31, 2024 | `January 2025` | Year rollover - December 26+ → January next year |

## Implementation

### Core Function

```typescript
const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

/**
 * Returns the payroll sheet name based on the 26th-25th payroll period logic.
 * 
 * Payroll Period Logic:
 * - Attendance from the 26th of one month to the 25th of the next month
 *   belongs to the sheet named after the ENDING month.
 * 
 * Examples:
 * - Jan 1-25 → "January 2025" sheet
 * - Jan 26-31 → "February 2025" sheet (rolls into next month)
 * - Feb 1-25 → "February 2025" sheet
 * - Feb 26-28 → "March 2025" sheet (rolls into next month)
 * - Dec 26-31 → "January 2025" sheet (year rollover)
 * 
 * @param referenceDate - The date to determine the payroll month for (defaults to now)
 * @returns The sheet name in format "MonthName YYYY" (e.g., "February 2025")
 */
export const getPayrollMonthSheetName = (referenceDate: Date = new Date()): string => {
  // Convert to Jakarta timezone (UTC+7) for consistent date calculation
  const jakartaTime = new Date(
    referenceDate.toLocaleString("en-US", { timeZone: "Asia/Jakarta" })
  );

  let year = jakartaTime.getFullYear();
  let monthIndex = jakartaTime.getMonth(); // 0-11 (0 = January, 11 = December)
  const dayOfMonth = jakartaTime.getDate(); // 1-31

  // If date is on or after the 26th, roll into the next month's sheet
  if (dayOfMonth >= 26) {
    monthIndex += 1;
    
    // Handle year rollover (December 26+ → January of next year)
    if (monthIndex > 11) {
      monthIndex = 0; // Reset to January
      year += 1;
    }
  }

  return `${monthNames[monthIndex]} ${year}`;
};
```

## Key Features

### 1. Timezone Handling
- All date calculations use **Jakarta timezone** (`Asia/Jakarta`, UTC+7)
- Ensures consistent behavior regardless of server timezone
- Prevents date boundary issues across different timezones

### 2. Cutoff Logic
- **Day 1-25**: Records go to the current month's sheet
- **Day 26-31**: Records go to the next month's sheet

### 3. Year Rollover
- December 26-31 automatically rolls into January of the next year
- Handles year transitions seamlessly

## Usage Examples

### Basic Usage
```typescript
// Get current payroll month sheet
const currentSheet = getPayrollMonthSheetName();
// Returns: "February 2025" (if today is Jan 26 - Feb 25, 2025)

// Get payroll month for a specific date
const specificDate = new Date('2025-01-26');
const sheetName = getPayrollMonthSheetName(specificDate);
// Returns: "February 2025"
```

### Real-World Scenarios

#### Scenario 1: Mid-Period Date
```typescript
const date = new Date('2025-02-15'); // February 15
const sheet = getPayrollMonthSheetName(date);
// Result: "February 2025"
// Explanation: Day 15 is before cutoff (26th), stays in February
```

#### Scenario 2: Post-Cutoff Date
```typescript
const date = new Date('2025-02-27'); // February 27
const sheet = getPayrollMonthSheetName(date);
// Result: "March 2025"
// Explanation: Day 27 is after cutoff (26th), rolls into March
```

#### Scenario 3: Year Transition
```typescript
const date = new Date('2024-12-30'); // December 30
const sheet = getPayrollMonthSheetName(date);
// Result: "January 2025"
// Explanation: December 26+ rolls into January of next year
```

## Integration with Google Sheets

The function is used in the Google Sheets API route (`/api/sheets/route.ts`) to:

1. **Determine Target Sheet**: Automatically selects the correct monthly sheet based on the attendance record's date
2. **Create Sheets**: Automatically creates new monthly sheets if they don't exist
3. **Organize Data**: Groups all attendance records from the same payroll period together

### Sheet Creation Flow
```
Attendance Record Received
    ↓
Extract Date from Record
    ↓
Call getPayrollMonthSheetName(date)
    ↓
Get/Create Sheet (e.g., "February 2025")
    ↓
Append Record to Sheet
```

## Testing

The logic is tested in `src/app/api/sheets/__tests__/payroll.test.ts` with the following cases:

- ✅ Dates before the 26th stay in current month
- ✅ Dates on/after the 26th roll into next month
- ✅ Year rollover from December to January
- ✅ Default behavior when no date is provided

## Business Rationale

This 26th-to-25th period structure is common in payroll systems because:

1. **Payroll Processing**: Allows payroll to be processed at the end of the month while capturing the full period
2. **Consistency**: Provides a fixed period length that doesn't vary with month length
3. **Reporting**: Makes it easier to generate monthly reports that align with payroll cycles
4. **Cutoff Alignment**: Ensures all attendance for a pay period is in one sheet before processing

## Notes

- The cutoff is **inclusive** of the 26th (day >= 26)
- All times are calculated in Jakarta timezone for consistency
- Sheet names use full month names (e.g., "February" not "Feb")
- Year is always 4 digits (e.g., "2025" not "25")

