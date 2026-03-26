# Head Division Permissions Troubleshooting

## Issue: Head Division users cannot add project members

### Root Cause Analysis

The issue was in `/app/Filament/Resources/ProjectResource.php` in the `getEloquentQuery()` method. Head Division users were being restricted to only see projects they were members of, preventing them from managing all projects.

### ✅ **FIXED**: Updated ProjectResource.php

The `getEloquentQuery()` method now includes Head Division users in the full access group:

```php
// Allow full access to super_admin and Head Division users
if (! $userIsSuperAdmin && ! $userIsHeadDivision) {
    $query->whereHas('members', function (Builder $query) use ($user) {
        $query->where('user_id', $user->id);
    });
}
```

## Debugging Commands

### 1. Debug Permissions
```bash
php artisan debug:head-division-permissions
```

### 2. Test Specific User
```bash
php artisan debug:head-division-permissions --user=USER_ID
```

### 3. Setup/Reset Permissions
```bash
php artisan setup:head-division-permissions
```

## Common Issues & Solutions

### Issue 1: "Add Member" button not visible
**Symptoms:**
- User can see projects but no "Add Member" button
- Members tab exists but no header actions

**Causes:**
- Missing `update_project` permission
- User not recognized as Head Division

**Solutions:**
```bash
# Grant permissions
php artisan permissions:grant-project-to-head-division

# Verify user role
php artisan debug:head-division-permissions --user=USER_ID
```

### Issue 2: Can't see all projects
**Symptoms:**
- Head Division user only sees projects they're member of
- Cannot access projects to add members

**Causes:**
- ProjectResource query restriction (FIXED above)
- Role not properly assigned

**Solutions:**
```bash
# Deploy the updated ProjectResource.php
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan permission:cache-reset

# Re-assign role if needed
php artisan setup:head-division-permissions --user=USER_ID
```

### Issue 3: User dropdown empty when adding members
**Symptoms:**
- "Add Member" button works
- User search dropdown shows no results

**Causes:**
- RoleHelper::scopeAllowedAssignees() not working
- User not properly authenticated

**Solutions:**
```bash
# Test RoleHelper functions
php artisan debug:head-division-permissions --user=USER_ID

# Check if user has Head Division role
php artisan tinker
> $user = App\Models\User::find(USER_ID);
> App\Helpers\RoleHelper::isHeadDivision($user);
> App\Helpers\RoleHelper::scopeAllowedAssignees(App\Models\User::query(), $user)->count();
```

### Issue 4: "Assignment not allowed" error
**Symptoms:**
- Can select users but get error after adding
- User gets detached immediately

**Causes:**
- RoleHelper::canAssignTo() returning false
- Role not recognized properly

**Solutions:**
```bash
# Verify role assignment
php artisan users:list --role=Head Division

# Check RoleHelper logic
php artisan debug:head-division-permissions --user=USER_ID
```

## Verification Steps

### 1. Check Role Assignment
```bash
php artisan users:list --role=Head Division
```

### 2. Verify Permissions
```bash
php artisan permission:show | grep project | grep Head
```

### 3. Test in Browser
1. Log in as Head Division user
2. Go to Project Management → Projects
3. Should see ALL projects (not just member projects)
4. Open any project
5. Go to Members tab
6. Should see "Add Member" button
7. Click "Add Member" - should see user dropdown
8. Select and add a user - should work without errors

### 4. Test RoleHelper Functions
```bash
php artisan tinker
> $user = App\Models\User::find(USER_ID);
> App\Helpers\RoleHelper::isHeadDivision($user);  // Should return true
> App\Helpers\RoleHelper::canAssignTo(App\Models\User::first(), $user);  // Should return true
```

## Required Permissions for Head Division

Head Division users need these permissions:
- `view_any_project` - View all projects
- `view_project` - View project details  
- `update_project` - Edit projects (required for member management)
- All other project_* permissions

## Deployment Checklist

1. **Deploy updated files:**
   - `app/Filament/Resources/ProjectResource.php` (FIXED)
   - `app/Console/Commands/DebugHeadDivisionPermissions.php` (NEW)

2. **Run setup commands:**
   ```bash
   php artisan setup:head-division-permissions
   ```

3. **Clear caches:**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan permission:cache-reset
   ```

4. **Test with Head Division user:**
   - Verify project access
   - Test member addition/removal

5. **Monitor logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "head\|division\|role"
   ```

## Emergency Rollback

If issues persist, you can temporarily grant super_admin role:
```bash
php artisan tinker
> $user = App\Models\User::find(USER_ID);
> $user->assignRole('super_admin');
```

Remember to remove super_admin and fix the underlying issue:
```bash
> $user->removeRole('super_admin');
> $user->assignRole('Head Division');
```

## Support

If issues continue:
1. Run the debug command: `php artisan debug:head-division-permissions --user=USER_ID`
2. Check Laravel logs: `storage/logs/laravel.log`
3. Verify all deployment steps completed
4. Test with a fresh user assignment
