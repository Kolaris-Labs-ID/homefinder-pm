# Attendance Import Documentation

## Overview

The HRIS system supports two methods for importing attendance data:

1. **Google Sheets Format** (Recommended) - Import directly from your existing Google Sheets
2. **Standard HRIS Format** - Import using the standardized HRIS format

---

## Quick Links

- **Quick Start Guide:** [`QUICK_START_GOOGLE_SHEETS_IMPORT.md`](QUICK_START_GOOGLE_SHEETS_IMPORT.md) - 3-step process
- **Full Migration Guide:** [`ATTENDANCE_MIGRATION_GUIDE.md`](ATTENDANCE_MIGRATION_GUIDE.md) - Complete instructions
- **Format Reference:** [`GOOGLE_SHEETS_ATTENDANCE_FORMAT.md`](GOOGLE_SHEETS_ATTENDANCE_FORMAT.md) - Column details
- **Implementation Details:** [`GOOGLE_SHEETS_IMPORT_IMPLEMENTATION.md`](GOOGLE_SHEETS_IMPORT_IMPLEMENTATION.md) - Technical documentation

---

## Which Import Method Should I Use?

### Use Google Sheets Format If:

- ✅ You have existing attendance data in Google Sheets
- ✅ Your format matches: No, Name, Action, Date & Time, Location, Address, etc.
- ✅ You want the easiest migration path
- ✅ You don't want to convert your data

### Use Standard HRIS Format If:

- ✅ You're importing from other systems
- ✅ You need a standardized format
- ✅ You prefer email-based employee matching
- ✅ You want combined check-in/check-out rows

---

## Google Sheets Format

### Required Columns

- **Name** - Employee name (must match HRIS)
- **Action** - "Check In" or "Check Out"
- **Date & Time** - Full datetime (YYYY-MM-DD HH:MM:SS)

### Optional Columns

- Location, Address, City, State/Province, Country, Postal Code
- Latitude, Longitude (GPS coordinates)
- Photo URL
- Work Hours, Description

### How It Works

- Each row = one action (Check In OR Check Out)
- System automatically groups by employee and date
- Creates one attendance record per employee per day

### Example

```
Name      | Action     | Date & Time
----------|------------|-------------------
John Doe  | Check In   | 2024-01-15 08:30:00
John Doe  | Check Out  | 2024-01-15 17:30:00
```

Becomes:

```
Attendance Record:
- Employee: John Doe
- Date: 2024-01-15
- Check-in: 08:30:00
- Check-out: 17:30:00
```

---

## Standard HRIS Format

### Required Columns

- **employee_email** - Employee email (must match HRIS)
- **date** - Date (YYYY-MM-DD)

### Optional Columns

- check_in_time, check_out_time (HH:MM)
- mode (office/wfh)
- status (present/late/absent/etc.)
- GPS coordinates and addresses

### How It Works

- Each row = one complete attendance record
- Check-in and check-out in same row
- Uses email for employee matching

### Example

```
employee_email    | date       | check_in_time | check_out_time
------------------|------------|---------------|---------------
john@company.com  | 2024-01-15 | 08:30         | 17:30
```

---

## Import Process

### For Google Sheets Format:

1. Go to: https://pm.partnershub.co/admin/attendance-logs
2. Click: **"Import from Google Sheets"** (blue button)
3. Upload your Excel file
4. Review results

### For Standard HRIS Format:

1. Go to: https://pm.partnershub.co/admin/attendance-logs
2. Click: **"Import Attendance Logs"** (green button)
3. Upload your Excel file
4. Review results

---

## Documentation Files

| File                                     | Purpose                  | Audience   |
| ---------------------------------------- | ------------------------ | ---------- |
| `QUICK_START_GOOGLE_SHEETS_IMPORT.md`    | 3-step quick start       | End users  |
| `ATTENDANCE_MIGRATION_GUIDE.md`          | Complete migration guide | End users  |
| `GOOGLE_SHEETS_ATTENDANCE_FORMAT.md`     | Format reference         | End users  |
| `GOOGLE_SHEETS_IMPORT_IMPLEMENTATION.md` | Technical details        | Developers |
| `ATTENDANCE_IMPORT_README.md`            | This file - overview     | Everyone   |

---

## Common Questions

**Q: Can I import data with only check-ins (no check-outs)?**  
A: Yes, the system will create records with check-in data only.

**Q: What happens if I import the same data twice?**  
A: The system updates existing records instead of creating duplicates.

**Q: Do employee names need to match exactly?**  
A: Yes, names must match exactly (including spaces and capitalization).

**Q: Can I import multiple months at once?**  
A: Yes, but we recommend starting with a small batch to test first.

**Q: What if my Google Sheets has a different format?**  
A: You can either convert to the supported format or use the standard HRIS format.

**Q: Will this affect existing attendance records?**  
A: No, it only creates new records or updates records for the same employee and date.

---

## Support

For help:

1. Check the troubleshooting section in `ATTENDANCE_MIGRATION_GUIDE.md`
2. Review format details in `GOOGLE_SHEETS_ATTENDANCE_FORMAT.md`
3. Contact system administrator

---

## Next Steps After Import

1. ✅ Verify imported data in HRIS
2. ✅ Test payroll calculations
3. ✅ Test weekly allowance calculations
4. ✅ Train staff on HRIS attendance entry
5. ✅ Set up regular attendance monitoring

---

**Last Updated:** January 26, 2026  
**Version:** 1.0
