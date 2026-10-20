# Google Sheets Attendance Format - Quick Reference

## Supported Format

The HRIS system now accepts your Google Sheets attendance format directly!

### Column Structure

| Column # | Column Name    | Required | Description                     | Example                       |
| -------- | -------------- | -------- | ------------------------------- | ----------------------------- |
| 1        | No             | No       | Row number                      | 1, 2, 3...                    |
| 2        | Name           | ✅ Yes   | Employee name (must match HRIS) | John Doe                      |
| 3        | Action         | ✅ Yes   | Check In or Check Out           | Check In                      |
| 4        | Date & Time    | ✅ Yes   | Full datetime of action         | 2024-01-15 08:30:00           |
| 5        | Location       | No       | Location name                   | Office Building               |
| 6        | Address        | No       | Street address                  | Jl. Sudirman No. 123          |
| 7        | City           | No       | City name                       | Jakarta                       |
| 8        | State/Province | No       | State or province               | DKI Jakarta                   |
| 9        | Country        | No       | Country name                    | Indonesia                     |
| 10       | Postal Code    | No       | Postal/ZIP code                 | 12190                         |
| 11       | Latitude       | No       | GPS latitude                    | -6.2088                       |
| 12       | Longitude      | No       | GPS longitude                   | 106.8456                      |
| 13       | Photo URL      | No       | Link to photo                   | https://example.com/photo.jpg |
| 14       | Work Hours     | No       | Calculated hours (reference)    | 9 hours                       |
| 15       | Description    | No       | Notes or description            | Morning check-in              |

### Required Columns

Only 3 columns are required:

1. **Name** - Employee name (must match name in HRIS)
2. **Action** - Must be "Check In" or "Check Out"
3. **Date & Time** - Full datetime in format YYYY-MM-DD HH:MM:SS

### Action Values

The **Action** column accepts (case-insensitive):

- Check In / CheckIn / check in / CHECKIN / In
- Check Out / CheckOut / check out / CHECKOUT / Out

### Date & Time Formats

Supported formats:

- `YYYY-MM-DD HH:MM:SS` (e.g., 2024-01-15 08:30:00)
- `YYYY-MM-DD HH:MM` (e.g., 2024-01-15 08:30)
- `DD/MM/YYYY HH:MM:SS` (e.g., 15/01/2024 08:30:00)
- `DD/MM/YYYY HH:MM` (e.g., 15/01/2024 08:30)
- Excel serial numbers (automatically converted)

## How It Works

### 1. Separate Rows for Check-In and Check-Out

Each row represents ONE action (either check-in OR check-out):

```
Row 1: John Doe | Check In  | 2024-01-15 08:30:00 | ...
Row 2: John Doe | Check Out | 2024-01-15 17:30:00 | ...
Row 3: Jane Smith | Check In | 2024-01-15 09:00:00 | ...
```

### 2. Automatic Grouping

The system automatically groups check-ins and check-outs by:

- Employee name
- Date (extracted from Date & Time)

Result in HRIS:

```
Attendance Record 1:
- Employee: John Doe
- Date: 2024-01-15
- Check-in: 08:30:00
- Check-out: 17:30:00

Attendance Record 2:
- Employee: Jane Smith
- Date: 2024-01-15
- Check-in: 09:00:00
- Check-out: (not yet)
```

### 3. Address Building

The system combines address fields into a full address:

- Address + City + State/Province + Country + Postal Code
- Falls back to Location if address fields are empty

Example:

```
Input:
- Address: Jl. Sudirman No. 123
- City: Jakarta
- State/Province: DKI Jakarta
- Country: Indonesia
- Postal Code: 12190

Output:
- Full Address: "Jl. Sudirman No. 123, Jakarta, DKI Jakarta, Indonesia, 12190"
```

### 4. GPS Coordinates

Latitude and Longitude are preserved for:

- Check-in location (from Check In row)
- Check-out location (from Check Out row)

### 5. Photo URLs

Photo URLs are stored for:

- Check-in photo (from Check In row)
- Check-out photo (from Check Out row)

## Sample Data

### Minimal Example (Required Columns Only)

```csv
No,Name,Action,Date & Time
1,John Doe,Check In,2024-01-15 08:30:00
2,John Doe,Check Out,2024-01-15 17:30:00
3,Jane Smith,Check In,2024-01-15 09:00:00
4,Jane Smith,Check Out,2024-01-15 18:00:00
```

### Full Example (All Columns)

```csv
No,Name,Action,Date & Time,Location,Address,City,State/Province,Country,Postal Code,Latitude,Longitude,Photo URL,Work Hours,Description
1,John Doe,Check In,2024-01-15 08:30:00,Office Building,Jl. Sudirman No. 123,Jakarta,DKI Jakarta,Indonesia,12190,-6.2088,106.8456,https://example.com/photo1.jpg,,Morning check-in
2,John Doe,Check Out,2024-01-15 17:30:00,Office Building,Jl. Sudirman No. 123,Jakarta,DKI Jakarta,Indonesia,12190,-6.2088,106.8456,https://example.com/photo2.jpg,9 hours,End of day
3,Jane Smith,Check In,2024-01-15 09:00:00,Home Office,Jl. Gatot Subroto,Jakarta,DKI Jakarta,Indonesia,12930,-6.2297,106.8253,,,Work from home
4,Jane Smith,Check Out,2024-01-15 18:00:00,Home Office,Jl. Gatot Subroto,Jakarta,DKI Jakarta,Indonesia,12930,-6.2297,106.8253,,9 hours,
```

## Import Steps

1. **Export from Google Sheets**
    - File → Download → Microsoft Excel (.xlsx)

2. **Import to HRIS**
    - Go to https://your-homefinder-domain.example/admin/attendance-logs
    - Click "Import from Google Sheets" (blue button)
    - Upload your Excel file
    - Click Submit

3. **Review Results**
    - Check import summary
    - Verify imported records
    - Fix any errors and re-import if needed

## Common Errors

### "Employee not found"

- Employee name doesn't match HRIS
- Check for typos or extra spaces
- Verify employee exists and is active

### "Invalid date/time"

- Date & Time column is empty
- Format doesn't match supported formats
- Contains invalid date (e.g., 2024-13-45)

### "Invalid action"

- Action column is empty
- Contains value other than Check In/Check Out
- Check for typos

### "Row skipped"

- Missing required columns (Name, Action, or Date & Time)
- Employee not found
- Invalid date/time format

## Tips

1. **Employee Names:** Ensure names match exactly (including spaces and capitalization)
2. **Date Format:** Use YYYY-MM-DD HH:MM:SS for best compatibility
3. **Action Values:** "Check In" and "Check Out" (with space) are most common
4. **Test First:** Import a small sample (1 week) before importing all data
5. **Backup:** Keep original Google Sheets as backup

## Need Help?

- See full migration guide: `ATTENDANCE_MIGRATION_GUIDE.md`
- Download template from HRIS: Click "Download Google Sheets Template"
- Contact system administrator for support

---

**Last Updated:** January 26, 2026  
**Version:** 1.0
