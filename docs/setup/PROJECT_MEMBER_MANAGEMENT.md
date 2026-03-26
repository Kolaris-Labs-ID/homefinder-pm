# Project Member Management Setup

## Goal
Enable **ALL users** to add/remove members from projects they are members of, while restricting project visibility to only projects they belong to.

## Current Behavior After Setup

### ✅ **Regular Users**
- Can see **ONLY** projects they are members of
- Can add/remove members from those projects
- Cannot see projects they don't belong to

### ✅ **Head Division Users** 
- Can see **ONLY** projects they are members of (same as regular users)
- Can add/remove members from those projects
- **Cannot** see projects they don't belong to

### ✅ **Super Admin Users**
- Can see **ALL** projects
- Can add/remove members from any project
- Full system access

### ✅ **C-Level Users (CEO, COO, CTO, HR)**
- Can see **ALL** projects (like Super Admin)
- Can add/remove members from any project
- Full system access and project management capabilities

## Quick Setup Commands

### 1. Enable Member Management for All Users
```bash
php artisan project:enable-member-management
```

### 2. Enable for Specific Role Only
```bash
php artisan project:enable-member-management --role=member
```

### 3. Clear Caches
```bash
php artisan cache:clear
php artisan permission:cache-reset
```

## Technical Implementation

### 1. ProjectResource.php - Project Visibility
```php
// Users can only see projects they are members of (except super_admin)
if (! $userIsSuperAdmin) {
    $query->whereHas('members', function (Builder $query) {
        $query->where('user_id', auth()->id());
    });
}
```

### 2. RoleHelper.php - Member Assignment
```php
// All users can assign members to projects they can access
public static function canAssignTo(User $targetUser, ?User $assigner = null): bool
{
    // Head Division can assign to anyone
    if (self::isHeadDivision($assigner)) {
        return true;
    }
    
    // C-Level roles can only assign to Head Division
    if (self::isCLevel($assigner)) {
        return self::isHeadDivision($targetUser);
    }
    
    // All other users can assign to anyone
    return true;
}
```

### 3. MembersRelationManager.php - UI Controls
```php
// Uses RoleHelper to determine who can be assigned
->recordSelectOptionsQuery(
    fn (Builder $query) => RoleHelper::scopeAllowedAssignees($query)
)
```

## Required Permissions

Users need these permissions to manage project members:
- `view_any_project` - View project list
- `view_project` - View project details
- `update_project` - Edit project (required for member management)

## Testing Checklist

### 1. Test Regular User
```bash
# Create test user or use existing
php artisan users:list

# Test permissions
php artisan debug:head-division-permissions --user=USER_ID
```

**Browser Test:**
- Login as regular user
- Go to Project Management → Projects
- **Should see only projects you're member of**
- Open any project → Members tab
- **Should see "Add Member" button**
- Try adding/removing a member
- **Should work without errors**

### 2. Test Head Division User
**Browser Test:**
- Login as Head Division user
- Go to Project Management → Projects
- **Should see ONLY projects you are member of**
- Open any project you're member of → Members tab
- **Should see "Add Member" button**
- Can add any team member to those projects

### 3. Test C-Level User
**Browser Test:**
- Login as C-Level user (CEO, COO, CTO, or HR)
- Go to Project Management → Projects
- **Should see ALL projects** (like Super Admin)
- Open any project → Members tab
- **Should see "Add Member" button**
- Can add/remove members from any project

### 4. Test Super Admin
**Browser Test:**
- Login as super_admin
- Should have full access to all projects and features

## Troubleshooting

### Issue: "Add Member" button not visible
**Cause:** Missing `update_project` permission
**Fix:** 
```bash
php artisan project:enable-member-management --role=USER_ROLE
```

### Issue: Can't see any projects
**Cause:** User not member of any projects
**Fix:** Add user to at least one project

### Issue: "Assignment not allowed" error
**Cause:** RoleHelper logic issue
**Fix:**
```bash
php artisan debug:head-division-permissions --user=USER_ID
```

### Issue: User dropdown empty
**Cause:** RoleHelper::scopeAllowedAssignees() restriction
**Fix:** Verify RoleHelper logic allows user to see other users

## SSH Deployment Commands

```bash
# 1. Connect to server
ssh user@your-server.com
cd /path/to/partnershub-pm

# 2. Deploy updated files
# (Upload: RoleHelper.php, EnableProjectMemberManagement.php)

# 3. Enable member management
php artisan project:enable-member-management

# 4. Clear caches
php artisan cache:clear
php artisan permission:cache-reset
php artisan config:clear

# 5. Verify setup
php artisan debug:head-division-permissions
```

## Security Considerations

### ✅ **Safe By Design**
- Users can only see projects they belong to
- Project visibility is restricted at query level
- Member management only within accessible projects

### ⚠️ **Considerations**
- All users can add any team member to their projects
- Consider if you want to restrict this further
- You can modify RoleHelper to add more restrictions if needed

### 🔒 **Future Enhancements**
If you need more restrictions, modify RoleHelper:
```php
// Example: Only allow adding users from same department
if ($assigner->department !== $targetUser->department) {
    return false;
}
```

## Rollback Plan

If you need to revert changes:
```bash
# Remove project permissions from regular users
php artisan tinker
> $role = Spatie\Permission\Models\Role::where('name', 'member')->first();
> $role->revokePermissionTo(['update_project', 'view_any_project', 'view_project']);
```

## Summary

After setup:
- ✅ **All users** can manage members of projects they belong to
- ✅ **Project visibility** is restricted to member projects only  
- ✅ **Head Division** and **Super Admin** have broader access
- ✅ **Security** maintained through query-level restrictions

The system now allows collaborative project management while maintaining proper access controls.
