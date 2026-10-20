# Attendance API 500 Error Fix

## Problem Summary

The attendance API endpoints are returning 500 Internal Server Errors:

-   `POST /api/hris/attendance/check-in` - 500 ERROR
-   `GET /api/hris/attendance/wfh-quota` - 500 ERROR
-   `GET /api/hris/attendance/daily` - 500 ERROR

## Root Cause

**The authenticated user (jiuhengtech@gmail.com) does NOT have an associated employee record.**

The `AttendanceController` requires every user to have an `Employee` record, but some users (especially admin users created early) don't have one. When the controller tries to access `$user->employee`, it gets `null` and throws an error.

## Solution

### Step 1: Diagnose the Issue

Run the diagnostic script to identify all users without employee records:

```bash
php diagnose-attendance-api.php
```

Or check a specific user:

```bash
php diagnose-attendance-api.php jiuhengtech@gmail.com
```

### Step 2: Create Missing Employee Records

Run the fix script to automatically create employee records for all users:

```bash
php create-missing-employee-records.php
```

This script will:

-   Find all users without employee records
-   Create basic employee records for them
-   Set default values (hire_date = today, employment_status = permanent, is_active = true)

### Step 3: Verify the Fix

After running the script, test the API endpoints:

```bash
# Get a token first
curl -X POST https://your-homefinder-domain.example/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"jiuhengtech@gmail.com","password":"your-password"}'

# Test the endpoints with the token
curl -X GET https://your-homefinder-domain.example/api/hris/attendance/daily \
  -H "Authorization: Bearer YOUR_TOKEN"

curl -X GET https://your-homefinder-domain.example/api/hris/attendance/wfh-quota \
  -H "Authorization: Bearer YOUR_TOKEN"

curl -X POST https://your-homefinder-domain.example/api/hris/attendance/check-in \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"mode":"office","source":"mobile"}'
```

### Step 4: Update Employee Details (Optional)

After creating the basic employee records, you can update them with proper details in the admin panel:

-   Navigate to HRIS > Employees
-   Edit each employee record
-   Fill in: NIK, hire_date, department, position, etc.

## Technical Details

### What Changed

1. **Improved error handling in AttendanceController**

    - Better error messages when employee record is missing
    - Returns proper 404 instead of 500 error

2. **Created diagnostic tools**
    - `diagnose-attendance-api.php` - Check system health
    - `create-missing-employee-records.php` - Auto-fix missing records

### Database Schema

The `employees` table requires:

-   `user_id` (foreign key to users table, unique)
-   Other fields are nullable or have defaults

### Why This Happened

When the HRIS module was added, existing users (especially admin users) were not automatically given employee records. The attendance API assumes all users are employees, which caused the 500 errors.

## Prevention

To prevent this in the future:

1. **Create employee records when creating new users**

    - Update user creation logic to automatically create employee records
    - Or use a database trigger/observer

2. **Add validation in user seeder**

    - Ensure seeders create both user and employee records

3. **Monitor for missing records**
    - Add a health check endpoint
    - Alert when users without employee records are detected

## Testing

After applying the fix, verify:

1. ✓ All users have employee records
2. ✓ Attendance check-in works
3. ✓ Attendance check-out works
4. ✓ Daily attendance retrieval works
5. ✓ WFH quota calculation works
6. ✓ Attendance history works

## Rollback

If you need to rollback the employee records created by the script:

```sql
-- CAUTION: This will delete employee records created by the script
DELETE FROM employees WHERE nik IS NULL AND hire_date >= '2025-12-20';
```

## Support

If issues persist after running the fix:

1. Check Laravel logs: `tail -f storage/logs/laravel.log`
2. Check database connection: `php artisan tinker --execute="DB::connection()->getPdo();"`
3. Verify migrations: `php artisan migrate:status`
4. Check user authentication: Test `/api/hris/profile` endpoint first

## Related Files

-   `app/Http/Controllers/Api/Hris/AttendanceController.php` - Main controller
-   `app/Services/Hris/AttendanceService.php` - Business logic
-   `app/Models/Hris/Employee.php` - Employee model
-   `app/Models/User.php` - User model with employee relationship
-   `database/migrations/2025_11_21_000120_create_employees_table.php` - Schema
