# Attendance Log Migration Guide

## Overview

This guide will help you migrate historical attendance data from Google Sheets to the PartnersHub HRIS system for payroll and weekly allowance calculations.

**Source:** Google Sheets - https://docs.google.com/spreadsheets/d/1cRGp-G_aLf1OfB4g-UJhaENJd39Ng9dCO5UfcO1iDjE/edit  
**Destination:** HRIS Attendance Logs - https://pm.partnershub.co/admin/attendance-logs

---

## 🎉 NEW: Direct Google Sheets Format Import

**Good news!** The HRIS system now supports importing your Google Sheets data directly without any format conversion!

Your current Google Sheets format is now supported:

- **No**, **Name**, **Action**, **Date & Time**, **Location**, **Address**, **City**, **State/Province**, **Country**, **Postal Code**, **Latitude**, **Longitude**, **Photo URL**, **Work Hours**, **Description**

**Choose Your Import Method:**

- **Option A (Recommended):** Use the Google Sheets format importer - no conversion needed!
- **Option B:** Use the standard HRIS format importer - requires format conversion

---

## Prerequisites

Before starting the migration:

1. ✅ All employees must exist in the HRIS system
2. ✅ Employee names in Google Sheets must match names in HRIS
3. ✅ You have admin access to the HRIS system
4. ✅ Backup your Google Sheets data

---

## Option A: Import Using Google Sheets Format (Recommended)

### Step 1: Export from Google Sheets

1. Open your Google Sheets: https://docs.google.com/spreadsheets/d/1cRGp-G_aLf1OfB4g-UJhaENJd39Ng9dCO5UfcO1iDjE/edit
2. Select the sheet/tab with attendance data
3. Click **File** → **Download** → **Microsoft Excel (.xlsx)**
4. Save the file (e.g., `attendance_data.xlsx`)

**That's it!** No format conversion needed.

### Step 2: Verify Employee Names

Before importing, ensure employee names in your Google Sheets match the names in HRIS:

1. Go to https://pm.partnershub.co/admin/employees
2. Check that all employee names match exactly
3. Fix any mismatches (typos, different name formats, etc.)

**Common Issues:**

- "John Doe" vs "Doe, John"
- "Jane" vs "Jane Smith"
- Extra spaces or special characters

### Step 3: Import to HRIS

1. Navigate to https://pm.partnershub.co/admin/attendance-logs
2. Click the **"Import from Google Sheets"** button (blue button)
3. (Optional) Click **"Download Google Sheets Template"** to see the expected format
4. Click **"Choose File"** and select your exported Excel file
5. Click **"Submit"** or **"Import"**
6. Wait for the import process to complete

### Step 4: Review Import Results

After import completes, you'll see a summary:

- ✅ Number of records imported/updated
- ⚠️ Number of rows skipped
- ❌ Any error messages

**What the importer does:**

- Reads each row as a separate action (Check In or Check Out)
- Automatically groups check-ins and check-outs by employee and date
- Creates or updates attendance records
- Preserves GPS coordinates, addresses, and photo URLs
- Skips rows with missing required data

### Step 5: Verify Data

1. Go to https://pm.partnershub.co/admin/attendance-logs
2. Filter by date range to see imported data
3. Click on a few records to verify details
4. Check that check-in and check-out times are correct

---

## Option B: Import Using Standard HRIS Format

If you prefer to use the standard HRIS format, follow these steps:

### Step 1: Download HRIS Template

1. Navigate to https://pm.partnershub.co/admin/attendance-logs
2. Click the **"Import Attendance Logs"** button (green button)
3. Click **"Download Import Template"**
4. Open the template to see the required format

### Step 2: Convert Your Data

The HRIS format requires these columns:

| Column Name           | Required | Format     | Example                    |
| --------------------- | -------- | ---------- | -------------------------- |
| `employee_email`      | ✅ Yes   | Email      | `john@company.com`         |
| `date`                | ✅ Yes   | YYYY-MM-DD | `2024-01-15`               |
| `check_in_time`       | Optional | HH:MM      | `08:30`                    |
| `check_out_time`      | Optional | HH:MM      | `17:30`                    |
| `mode`                | Optional | Text       | `office` or `wfh`          |
| `status`              | Optional | Text       | `present`                  |
| `source`              | Optional | Text       | `import`                   |
| `check_in_latitude`   | Optional | Decimal    | `-6.2088`                  |
| `check_in_longitude`  | Optional | Decimal    | `106.8456`                 |
| `check_in_address`    | Optional | Text       | `Office Building, Jakarta` |
| `check_out_latitude`  | Optional | Decimal    | `-6.2088`                  |
| `check_out_longitude` | Optional | Decimal    | `106.8456`                 |
| `check_out_address`   | Optional | Text       | `Office Building, Jakarta` |

**Conversion Steps:**

1. Create a new sheet in your Google Sheets
2. Add the header row with column names above
3. Transform your data:
    - Map employee names to email addresses
    - Group Check In and Check Out rows into single rows
    - Extract date from "Date & Time" column
    - Extract time from "Date & Time" column
    - Combine address fields into single address column
4. Export as Excel (.xlsx)

### Step 3: Import to HRIS

1. Navigate to https://pm.partnershub.co/admin/attendance-logs
2. Click **"Import Attendance Logs"** (green button)
3. Upload your converted file
4. Review import results

---

## Understanding Your Google Sheets Format

Your current format has these columns:

| Column         | Description               | Used For                |
| -------------- | ------------------------- | ----------------------- |
| No             | Row number                | Reference only          |
| Name           | Employee name             | Matching employee       |
| Action         | "Check In" or "Check Out" | Determining action type |
| Date & Time    | Full datetime             | Date and time of action |
| Location       | Location name             | Address field           |
| Address        | Street address            | Address field           |
| City           | City name                 | Address field           |
| State/Province | State/Province            | Address field           |
| Country        | Country name              | Address field           |
| Postal Code    | Postal code               | Address field           |
| Latitude       | GPS latitude              | GPS coordinates         |
| Longitude      | GPS longitude             | GPS coordinates         |
| Photo URL      | Link to photo             | Photo storage           |
| Work Hours     | Calculated hours          | Reference only          |
| Description    | Notes                     | Metadata                |

**How the importer handles your data:**

1. **Separate Rows:** Each row is one action (Check In OR Check Out)
2. **Automatic Grouping:** The system automatically groups check-ins and check-outs by employee and date
3. **Address Building:** Combines Address, City, State/Province, Country, Postal Code into full address
4. **GPS Coordinates:** Preserves latitude and longitude
5. **Photos:** Stores photo URLs (if provided)

**Example:**

Your Google Sheets:

```
Row 1: John Doe | Check In  | 2024-01-15 08:30:00 | ...
Row 2: John Doe | Check Out | 2024-01-15 17:30:00 | ...
```

Becomes in HRIS:

```
One attendance record for John Doe on 2024-01-15
- Check-in: 08:30:00
- Check-out: 17:30:00
```

---

## Troubleshooting

### Issue: "Employee not found"

**Solution:**

- Verify employee name exists in HRIS
- Check for typos or extra spaces
- Ensure name format matches (e.g., "John Doe" not "Doe, John")
- Employee must be active in the system

### Issue: "Invalid date/time format"

**Solution:**

- Ensure Date & Time column has valid datetime
- Supported formats: YYYY-MM-DD HH:MM:SS, DD/MM/YYYY HH:MM:SS
- Check for empty cells

### Issue: "Invalid action"

**Solution:**

- Action column must contain "Check In" or "Check Out"
- Check for typos or extra spaces
- Case-insensitive (checkin, CheckIn, check in all work)

### Issue: "Rows skipped"

**Solution:**

- Check that Name, Action, and Date & Time are not empty
- Verify employee names match HRIS
- Review error messages for specific issues

### Issue: "No records imported"

**Solution:**

- Ensure file has data rows (not just headers)
- Check that required columns exist
- Verify file format is Excel or CSV
- Review validation errors in notification

---

## Best Practices

1. **Start Small:** Import one week of data first to test
2. **Backup First:** Keep original Google Sheets data
3. **Verify Names:** Double-check employee names before import
4. **Check Results:** Review imported data after each batch
5. **Use Google Sheets Format:** Easier and faster than converting
6. **Batch Import:** If you have large datasets, split into monthly batches
7. **Document Issues:** Note any problems for future reference

---

## Import Checklist

### For Google Sheets Format Import:

- [ ] Backed up original Google Sheets data
- [ ] Verified all employee names exist in HRIS
- [ ] Exported Google Sheets as Excel (.xlsx)
- [ ] Imported file using "Import from Google Sheets" button
- [ ] Reviewed import results
- [ ] Verified sample records in HRIS
- [ ] Checked that check-ins and check-outs are grouped correctly
- [ ] Tested payroll calculations

### For Standard HRIS Format Import:

- [ ] Downloaded HRIS import template
- [ ] Converted Google Sheets data to HRIS format
- [ ] Verified employee emails exist in HRIS
- [ ] Grouped check-ins and check-outs into single rows
- [ ] Exported converted data as Excel (.xlsx)
- [ ] Imported file using "Import Attendance Logs" button
- [ ] Reviewed import results
- [ ] Verified sample records in HRIS
- [ ] Tested payroll calculations

---

## Comparison: Google Sheets vs HRIS Format

| Feature                        | Google Sheets Format | HRIS Format          |
| ------------------------------ | -------------------- | -------------------- |
| **Conversion Required**        | ❌ No                | ✅ Yes               |
| **Separate Check-In/Out Rows** | ✅ Yes               | ❌ No (combined)     |
| **Employee Identifier**        | Name                 | Email                |
| **Address Format**             | Multiple columns     | Single column        |
| **Ease of Use**                | ⭐⭐⭐⭐⭐ Easy      | ⭐⭐⭐ Moderate      |
| **Best For**                   | Direct migration     | Standardized imports |

**Recommendation:** Use Google Sheets format for initial migration, then use HRIS format for future imports if needed.

---

## Post-Migration

After successful migration:

1. ✅ Keep Google Sheets as backup (read-only)
2. ✅ Use HRIS for all new attendance entries
3. ✅ Train staff on HRIS attendance entry
4. ✅ Set up regular attendance monitoring
5. ✅ Configure payroll to use HRIS attendance data
6. ✅ Test weekly allowance calculations

---

## Additional Resources

- **HRIS Documentation:** Check `/docs` folder for user guides
- **Employee Management:** https://pm.partnershub.co/admin/employees
- **Payroll Module:** https://pm.partnershub.co/admin/payroll-records
- **Weekly Allowance:** https://pm.partnershub.co/admin/weekly-allowances

---

## Support

If you encounter issues during migration:

1. Check the troubleshooting section above
2. Review import error messages carefully
3. Verify data format matches requirements
4. Contact system administrator for assistance

---

**Last Updated:** January 26, 2026  
**Version:** 2.0 (Added Google Sheets format support)
