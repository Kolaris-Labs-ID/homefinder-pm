# Quick Fix: Attendance API 500 Errors

## TL;DR

**Problem:** User has no employee record  
**Fix:** Run `php create-missing-employee-records.php`  
**Time:** 2 minutes

---

## On Production Server

```bash
# 1. Diagnose
php diagnose-attendance-api.php

# 2. Fix
php create-missing-employee-records.php

# 3. Deploy code updates
git pull
php artisan config:clear

# 4. Test
curl -X GET https://your-homefinder-domain.example/api/hris/attendance/daily \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Expected Output

```
=== Creating Missing Employee Records ===

Found 1 users without employee records

Creating employee record for: superadmin (jiuhengtech@gmail.com)
  ✓ Created employee ID: 16

=== SUCCESS ===
Created 1 employee records
```

## Verify Fix

```bash
php diagnose-attendance-api.php jiuhengtech@gmail.com
```

Should show:

```
✓ User found: superadmin (ID: 1)
✓ Employee record found (ID: 16)
✓ Attendance logs: 0
```

## Done!

The attendance API endpoints should now return proper responses instead of 500 errors.

---

## What Was Fixed

1. ✅ Created missing employee records
2. ✅ Improved error handling (returns 404 instead of 500)
3. ✅ Added logging for debugging
4. ✅ Better error messages

## Next Steps

Update employee details in admin panel:

-   HRIS > Employees > Edit
-   Fill in: NIK, hire date, department, position

---

## Troubleshooting

**Still getting 500 errors?**

```bash
# Check logs
tail -f storage/logs/laravel.log

# Verify employee exists
php artisan tinker --execute="User::find(1)->employee"

# Clear cache
php artisan config:clear
php artisan cache:clear
```

**Need to rollback?**

```sql
DELETE FROM employees WHERE nik IS NULL AND hire_date >= '2025-12-20';
```
