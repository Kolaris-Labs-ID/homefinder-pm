# Quick Start: Import Attendance from Google Sheets

## 3-Step Process

### Step 1: Export from Google Sheets

1. Open: https://docs.google.com/spreadsheets/d/1cRGp-G_aLf1OfB4g-UJhaENJd39Ng9dCO5UfcO1iDjE/edit
2. Click: **File** → **Download** → **Microsoft Excel (.xlsx)**
3. Save the file

### Step 2: Import to HRIS

1. Go to: https://your-homefinder-domain.example/admin/attendance-logs
2. Click: **"Import from Google Sheets"** (blue button)
3. Upload your Excel file
4. Click: **Submit**

### Step 3: Verify

1. Check the import summary (shows imported/skipped counts)
2. Review a few records to confirm data is correct
3. Done! ✅

---

## What You Need

✅ Your Google Sheets must have these columns:

- **Name** (employee name)
- **Action** (Check In or Check Out)
- **Date & Time** (when the action happened)

✅ Employee names must match names in HRIS

---

## Your Current Format is Supported!

Your Google Sheets columns:

```
No, Name, Action, Date & Time, Location, Address, City,
State/Province, Country, Postal Code, Latitude, Longitude,
Photo URL, Work Hours, Description
```

**No conversion needed!** Just export and import.

---

## Example Data

```
Name         | Action     | Date & Time
-------------|------------|-------------------
John Doe     | Check In   | 2024-01-15 08:30:00
John Doe     | Check Out  | 2024-01-15 17:30:00
Jane Smith   | Check In   | 2024-01-15 09:00:00
```

The system automatically groups check-ins and check-outs by employee and date.

---

## Troubleshooting

**"Employee not found"**
→ Check that employee names match exactly in HRIS

**"Invalid date/time"**
→ Ensure Date & Time column has valid datetime (YYYY-MM-DD HH:MM:SS)

**"Rows skipped"**
→ Check that Name, Action, and Date & Time are not empty

---

## Need More Help?

- Full guide: `ATTENDANCE_MIGRATION_GUIDE.md`
- Format reference: `GOOGLE_SHEETS_ATTENDANCE_FORMAT.md`
- Download template from HRIS for examples

---

**That's it!** Your attendance data will be ready for payroll and weekly allowance calculations.
