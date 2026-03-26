# Head Division Role Setup Commands

These commands are designed to be executed via SSH on your production server to grant project management permissions to Head Division users.

## Available Commands

### 1. Complete Setup (Recommended)
```bash
php artisan setup:head-division-permissions
```
This command:
- Creates the Head Division role if it doesn't exist
- Grants all project-related permissions to the role
- Shows next steps for user assignment

### 2. List All Users
```bash
php artisan users:list
```
Shows all users with their IDs, roles, and division information.

### 3. Assign Head Division Role to User
```bash
php artisan setup:head-division-permissions --user=USER_ID
```
Replace `USER_ID` with the actual user ID from the list.

### 4. List Current Head Division Users
```bash
php artisan setup:head-division-permissions --list
```
Shows all users who currently have the Head Division role.

### 5. Individual Commands (Alternative)

#### Grant Permissions Only
```bash
php artisan permissions:grant-project-to-head-division
```

#### Assign Role Only
```bash
php artisan role:assign-head-division USER_ID
```

## SSH Usage Examples

### Step 1: Connect to Server
```bash
ssh user@your-server.com
cd /path/to/your/laravel/project
```

### Step 2: List Users to Find User ID
```bash
php artisan users:list
```

### Step 3: Setup Head Division Permissions
```bash
php artisan setup:head-division-permissions
```

### Step 4: Assign Role to Specific User
```bash
php artisan setup:head-division-permissions --user=123
```

### Step 5: Verify Setup
```bash
php artisan setup:head-division-permissions --list
```

## What Head Division Users Can Do

After setup, users with Head Division role can:

✅ **Edit project members** - Add/remove team members from projects  
✅ **Add project members** - Assign new users to projects  
✅ **View all projects** - Access project management interface  
✅ **Manage project settings** - Edit project details  
✅ **Assign any user** - No restrictions on who they can assign to projects  

## Verification

To verify permissions are working:

1. **Check Role Permissions:**
```bash
php artisan permission:show | grep project
```

2. **Test in Admin Panel:**
   - Log in as a Head Division user
   - Go to Project Management → Projects
   - Open any project
   - Navigate to the "Members" tab
   - Verify you can add/remove members

3. **Check RoleHelper Logic:**
The system uses `RoleHelper::isHeadDivision()` to check permissions. Head Division users have full assignment capabilities.

## Troubleshooting

### Role Not Found
If you get "Role not found" error:
```bash
php artisan shield:generate --all
php artisan setup:head-division-permissions
```

### Permissions Not Working
Clear caches:
```bash
php artisan permission:cache-reset
php artisan cache:clear
php artisan config:clear
```

### User Can't See Projects
Ensure the user is also a project member:
```bash
php artisan tinker
> $user = App\Models\User::find(USER_ID);
> $project = App\Models\Project::find(PROJECT_ID);
> $project->members()->attach($user->id);
```

## Security Notes

- Head Division role can assign ANY user to projects
- They can see all projects regardless of project membership
- Consider carefully who gets this role
- The role respects the existing `RoleHelper` logic in the codebase

## Production Deployment

1. Deploy the new command files to your server
2. Run the setup commands
3. Test with a Head Division user
4. Monitor logs for any permission issues

The commands are idempotent - running them multiple times won't cause issues.
