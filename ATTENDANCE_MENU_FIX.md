# Attendance Menu Not Showing - Fix Guide

## Issue

The Attendance Logs menu is not appearing in the HRIS section of Filament.

## Root Cause

The AttendanceLogResource requires permissions that may not be:

1. Generated in the database
2. Assigned to your user role

## Solution

### Step 1: Regenerate Shield Permissions

Run this command to regenerate all resource permissions:

```bash
php artisan shield:generate --all
```

This will create permissions for all Filament resources, including:

-   `view_any_attendance::log`
-   `view_attendance::log`
-   `create_attendance::log`
-   `update_attendance::log`
-   `delete_attendance::log`
-   etc.

### Step 2: Assign Permissions to Your Role

**Option A: Via Filament UI (Recommended)**

1. Log in as super_admin
2. Go to **Shield** → **Roles**
3. Edit your role (e.g., `hr-admin`, `super_admin`)
4. Check the permissions for **Attendance Log**:
    - ✓ View Any Attendance Log
    - ✓ View Attendance Log
    - ✓ Create Attendance Log
    - ✓ Update Attendance Log
    - ✓ Delete Attendance Log
5. Save

**Option B: Via Artisan Command**

For super_admin role:

```bash
php artisan shield:super-admin
```

For specific role (e.g., hr-admin):

```bash
php artisan tinker
```

Then run:

```php
$role = Spatie\Permission\Models\Role::where('name', 'hr-admin')->first();
$permissions = Spatie\Permission\Models\Permission::where('name', 'like', '%attendance::log%')->get();
$role->givePermissionTo($permissions);
```

### Step 3: Clear Cache

```bash
php artisan cache:clear
php artisan config:clear
php artisan permission:cache-reset
```

### Step 4: Verify

1. Log out and log back in
2. Check the HRIS menu - you should now see **Attendance Logs**

## Expected Menu Structure

```
HRIS
├── Departments
├── Employees
├── Leave Requests
├── Leave Types
├── Positions
└── Attendance Logs  ← Should appear here
```

## Troubleshooting

### Menu Still Not Showing?

1. **Check if resource is registered:**

    ```bash
    php artisan route:list | grep attendance
    ```

    You should see routes like:

    - `filament.admin.resources.attendance-logs.index`
    - `filament.admin.resources.attendance-logs.create`
    - etc.

2. **Check your user's permissions:**

    ```bash
    php artisan tinker
    ```

    ```php
    $user = App\Models\User::find(YOUR_USER_ID);
    $user->getAllPermissions()->pluck('name');
    ```

    Look for `view_any_attendance::log` in the list.

3. **Check the policy is registered:**
   Look in `app/Providers/AuthServiceProvider.php` for:

    ```php
    protected $policies = [
        AttendanceLog::class => AttendanceLogPolicy::class,
    ];
    ```

4. **Verify navigation group:**
   The resource should have:
    ```php
    protected static ?string $navigationGroup = 'HRIS';
    ```

## Quick Fix Script

Create a temporary command to assign all attendance permissions:

```bash
php artisan tinker
```

```php
// Get all attendance log permissions
$permissions = Spatie\Permission\Models\Permission::where('name', 'like', '%attendance::log%')->get();

// Assign to super_admin role
$superAdmin = Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
$superAdmin->givePermissionTo($permissions);

// Assign to hr-admin role (if exists)
$hrAdmin = Spatie\Permission\Models\Role::where('name', 'hr-admin')->first();
if ($hrAdmin) {
    $hrAdmin->givePermissionTo($permissions);
}

// Assign to hr-staff role (if exists)
$hrStaff = Spatie\Permission\Models\Role::where('name', 'hr-staff')->first();
if ($hrStaff) {
    $hrStaff->givePermissionTo($permissions);
}

echo "Permissions assigned successfully!";
```

Then clear cache and refresh your browser.

---

**Date:** December 15, 2025
**Issue:** Attendance menu not visible in Filament HRIS section
**Status:** Awaiting permission generation and assignment
