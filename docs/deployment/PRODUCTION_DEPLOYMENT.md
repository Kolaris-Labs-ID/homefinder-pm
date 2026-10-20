# Production Deployment Guide - HRIS Permissions Setup

## Prerequisites

-   SSH access to your VPS
-   Your Laravel app is already deployed
-   Database is accessible
-   You have sudo/root access (if needed)

---

## Method 1: Using Artisan Commands (Recommended)

This is the safest and simplest method for production.

### Step 1: SSH into your VPS

```bash
ssh your-user@your-vps-ip
```

### Step 2: Navigate to your Laravel project

```bash
cd /path/to/your/laravel/project
# Example: cd /var/www/homefinder-pm
```

### Step 3: Run the permission setup commands

```bash
# Generate all Shield permissions
php artisan shield:generate --all

# Assign all permissions to super_admin role
php artisan shield:super-admin

# Clear caches
php artisan permission:cache-reset
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Restart queue workers (if using queues)
php artisan queue:restart
```

### Step 4: Verify permissions were created

```bash
php artisan tinker
```

Then in tinker:

```php
// Check if permissions exist
$count = Spatie\Permission\Models\Permission::where('name', 'like', '%attendance%')->count();
echo "Attendance permissions: $count\n";

$count = Spatie\Permission\Models\Permission::where('name', 'like', '%salary%')->count();
echo "Salary permissions: $count\n";

$count = Spatie\Permission\Models\Permission::where('name', 'like', '%payrun%')->count();
echo "Payrun permissions: $count\n";

// Exit tinker
exit
```

---

## Method 2: Using the Setup Script

If you want to use the `setup-hris-permissions.php` script:

### Step 1: Upload the script to your VPS

From your local machine:

```bash
scp setup-hris-permissions.php your-user@your-vps-ip:/path/to/your/laravel/project/
```

Or use Git:

```bash
# On your local machine
git add setup-hris-permissions.php
git commit -m "Add HRIS permissions setup script"
git push

# On your VPS
cd /path/to/your/laravel/project
git pull
```

### Step 2: Run the script

```bash
cd /path/to/your/laravel/project
php setup-hris-permissions.php
```

---

## Method 3: Manual Database Approach (If Artisan Fails)

If for some reason Artisan commands don't work, you can manually run SQL:

### Step 1: Connect to your database

```bash
# For MySQL
mysql -u your_db_user -p your_database_name

# For PostgreSQL
psql -U your_db_user -d your_database_name
```

### Step 2: Check if permissions table exists

```sql
SELECT COUNT(*) FROM permissions WHERE name LIKE '%attendance%';
```

If it returns 0, permissions haven't been generated yet.

---

## Complete Deployment Workflow

Here's the full workflow for deploying your code with HRIS features:

```bash
# 1. SSH into VPS
ssh your-user@your-vps-ip

# 2. Navigate to project
cd /var/www/homefinder-pm  # Adjust path

# 3. Put application in maintenance mode
php artisan down

# 4. Pull latest code
git pull origin main  # or your branch name

# 5. Install/update dependencies
composer install --no-dev --optimize-autoloader

# 6. Run migrations (if any new ones)
php artisan migrate --force

# 7. Generate Shield permissions
php artisan shield:generate --all

# 8. Assign permissions to super_admin
php artisan shield:super-admin

# 9. Clear and optimize caches
php artisan permission:cache-reset
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# 10. Restart services
php artisan queue:restart
sudo systemctl restart php8.2-fpm  # Adjust PHP version
sudo systemctl restart nginx       # or apache2

# 11. Bring application back online
php artisan up
```

---

## Troubleshooting

### Issue: "shield:generate command not found"

**Solution:** Make sure Filament Shield is installed:

```bash
composer require bezhansalleh/filament-shield
php artisan vendor:publish --tag=filament-shield-config
```

### Issue: "Permission denied" errors

**Solution:** Fix file permissions:

```bash
sudo chown -R www-data:www-data /path/to/your/laravel/project
sudo chmod -R 755 /path/to/your/laravel/project/storage
sudo chmod -R 755 /path/to/your/laravel/project/bootstrap/cache
```

### Issue: Menus still not showing after setup

**Solutions:**

1. **Check your user has the super_admin role:**

```bash
php artisan tinker
```

```php
$user = App\Models\User::where('email', 'your@email.com')->first();
$user->roles->pluck('name'); // Should include 'super_admin'

// If not, assign the role:
$user->assignRole('super_admin');
exit
```

2. **Clear browser cache:**

    - Press `Ctrl + Shift + R` (Windows/Linux)
    - Press `Cmd + Shift + R` (Mac)
    - Or use incognito/private mode

3. **Check if resources are registered:**

```bash
php artisan route:list | grep attendance
php artisan route:list | grep salary
php artisan route:list | grep payrun
```

You should see Filament routes for these resources.

### Issue: Database connection errors

**Solution:** Check your `.env` file:

```bash
cat .env | grep DB_
```

Make sure database credentials are correct.

---

## Post-Deployment Verification

### 1. Check logs for errors

```bash
tail -f storage/logs/laravel.log
```

### 2. Test the application

1. Log out of Filament
2. Log back in
3. Check sidebar for new menu groups:
    - HRIS (should now include Attendance Logs)
    - Payroll (new group)
    - Recruitment (new group)
    - Performance (new group)
    - Training (new group)

### 3. Verify permissions in database

```bash
php artisan tinker
```

```php
// Count all permissions
echo Spatie\Permission\Models\Permission::count() . " total permissions\n";

// Check super_admin has permissions
$role = Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
echo $role->permissions->count() . " permissions for super_admin\n";

exit
```

---

## Security Notes

1. **Always backup your database before running migrations or permission changes:**

```bash
# MySQL
mysqldump -u user -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# PostgreSQL
pg_dump -U user database_name > backup_$(date +%Y%m%d_%H%M%S).sql
```

2. **Use maintenance mode during deployment** to prevent users from accessing the site during updates.

3. **Test on staging first** if you have a staging environment.

4. **Monitor logs** after deployment for any errors.

---

## Quick Reference Commands

```bash
# Generate permissions
php artisan shield:generate --all

# Assign to super_admin
php artisan shield:super-admin

# Clear caches
php artisan permission:cache-reset && php artisan cache:clear

# Restart services
php artisan queue:restart
sudo systemctl restart php8.2-fpm nginx

# Check logs
tail -f storage/logs/laravel.log

# Verify permissions
php artisan tinker --execute="echo Spatie\Permission\Models\Permission::count() . ' permissions';"
```

---

## Support

If you encounter issues:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check web server logs:
    - Nginx: `/var/log/nginx/error.log`
    - Apache: `/var/log/apache2/error.log`
3. Check PHP-FPM logs: `/var/log/php8.2-fpm.log`

---

**Last Updated:** December 16, 2025
