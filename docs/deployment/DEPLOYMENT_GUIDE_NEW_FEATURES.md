# Production Deployment Guide - New Features

## Weekly Allowance System & Enhanced Payroll Settings

**Date**: January 26, 2026  
**Features**: Weekly Allowance Batches, Payroll Settings, Prorate Calculations  
**Target**: Production VPS Server (`your-homefinder-domain.example`)

---

## 📋 Pre-Deployment Checklist

### ✅ Before You Start

- [ ] All tests passed (98 new feature tests ✓)
- [ ] Database backup completed
- [ ] Maintenance window scheduled
- [ ] Team notified about deployment
- [ ] Rollback plan prepared
- [ ] SSH access to production server verified
- [ ] Git repository up to date

### ⚠️ Important Notes

- **Downtime**: ~5-10 minutes during deployment
- **Database Changes**: 8 new migrations will run
- **New Features**: Weekly allowance system, enhanced payroll settings
- **User Impact**: No data loss, new features available immediately

---

## 🚀 Step-by-Step Deployment Process

### Step 1: Backup Production Database

**Time**: 2-3 minutes

```bash
# SSH into your VPS
ssh root@your-vps-ip

# Navigate to project directory
cd /var/www/homefinder-pm

# Create backup directory if not exists
mkdir -p /var/backups/homefinder-pm

# Backup database
mysqldump -u pm_user -p homefinder_pm > /var/backups/homefinder-pm/backup_$(date +%Y%m%d_%H%M%S).sql

# Verify backup was created
ls -lh /var/backups/homefinder-pm/
```

**Expected Output**: You should see a new `.sql` file with current timestamp

---

### Step 2: Put Application in Maintenance Mode

**Time**: 10 seconds

```bash
# Enable maintenance mode
php artisan down --message="System upgrade in progress. Back in 10 minutes." --retry=60
```

**What happens**: Users will see a maintenance page. They can retry after 60 seconds.

**Verify**: Visit `https://your-homefinder-domain.example` in browser - should show maintenance page

---

### Step 3: Pull Latest Code from Repository

**Time**: 30 seconds

```bash
# Check current branch
git branch

# Fetch latest changes
git fetch origin

# Pull latest code (adjust branch name if needed)
git pull origin main

# Verify latest commit
git log -1
```

**Expected Output**: Should show the latest commit with new features

**Troubleshooting**:

- If you get merge conflicts: `git stash` then `git pull` then `git stash pop`
- If you modified files locally: Commit or stash them first

---

### Step 4: Update Dependencies

**Time**: 1-2 minutes

```bash
# Update Composer dependencies
composer install --no-dev --optimize-autoloader

# Update NPM dependencies (if any frontend changes)
npm ci

# Build frontend assets (if needed)
npm run build
```

**Expected Output**:

- Composer: "Generating optimized autoload files"
- NPM: "added X packages"

---

### Step 5: Run Database Migrations

**Time**: 30 seconds

```bash
# Run migrations
php artisan migrate --force

# Expected migrations to run:
# - 2026_01_25_000001_create_payroll_settings_table
# - 2026_01_25_000002_add_prorate_fields_to_payroll_records_table
# - 2026_01_25_000003_add_payroll_options_to_salary_structures_table
# - 2026_01_26_000001_create_weekly_allowance_batches_table
# - 2026_01_26_000002_create_weekly_allowance_payments_table
# - 2026_01_26_000003_add_weekly_allowance_enabled_to_salary_structures
# - 2026_01_26_000004_add_weekly_allowance_fields_to_payroll_records
```

**Expected Output**:

```
Migrating: 2026_01_25_000001_create_payroll_settings_table
Migrated:  2026_01_25_000001_create_payroll_settings_table (XX.XXms)
...
```

**Verify Migrations**:

```bash
# Check if all migrations ran
php artisan migrate:status

# Should show all 8 new migrations as "Ran"
```

---

### Step 6: Seed Payroll Settings (Important!)

**Time**: 5 seconds

```bash
# Seed default payroll settings
php artisan db:seed --class=PayrollSettingSeeder
```

**What this does**: Creates default BPJS rates, tax brackets, and payroll configuration

**Expected Output**:

```
Database seeding completed successfully.
```

**Verify**:

```bash
php artisan tinker
```

```php
// Check if settings were created
\App\Models\Hris\PayrollSetting::count();
// Should return > 0

exit
```

---

### Step 7: Generate Permissions for New Features

**Time**: 10 seconds

```bash
# Generate Shield permissions for new resources
php artisan shield:generate --all

# Assign all permissions to super_admin
php artisan shield:super-admin
```

**Expected Output**:

```
Successfully generated permissions for all resources.
```

**Verify**:

```bash
php artisan tinker
```

```php
// Check for new permissions
Spatie\Permission\Models\Permission::where('name', 'like', '%weekly_allowance%')->count();
// Should return > 0

Spatie\Permission\Models\Permission::where('name', 'like', '%payroll_setting%')->count();
// Should return > 0

exit
```

---

### Step 8: Clear and Optimize Caches

**Time**: 15 seconds

```bash
# Clear all caches
php artisan permission:cache-reset
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

**Expected Output**: Each command should complete without errors

---

### Step 9: Restart Services

**Time**: 10 seconds

```bash
# Restart queue workers
php artisan queue:restart

# Restart PHP-FPM (adjust version if needed)
sudo systemctl restart php8.2-fpm

# Restart Nginx
sudo systemctl restart nginx

# Restart Supervisor (for queue workers)
sudo supervisorctl restart laravel-worker:*
```

**Verify Services**:

```bash
# Check PHP-FPM status
sudo systemctl status php8.2-fpm

# Check Nginx status
sudo systemctl status nginx

# Check Supervisor status
sudo supervisorctl status
```

All should show "active (running)"

---

### Step 10: Bring Application Back Online

**Time**: 5 seconds

```bash
# Disable maintenance mode
php artisan up
```

**Verify**: Visit `https://your-homefinder-domain.example` - application should be accessible

---

### Step 11: Post-Deployment Verification

**Time**: 2-3 minutes

#### A. Check Application Logs

```bash
# Monitor logs for errors
tail -f storage/logs/laravel.log

# Press Ctrl+C to stop after 30 seconds if no errors
```

**What to look for**: No error messages appearing

#### B. Verify New Features in Admin Panel

1. **Log in to Filament Admin**: `https://your-homefinder-domain.example/admin`

2. **Check Payroll Settings**:
    - Navigate to **HRIS** → **Payroll Settings**
    - Should see list of settings (BPJS rates, tax brackets, etc.)
    - Try viewing/editing a setting

3. **Check Weekly Allowance**:
    - Navigate to **HRIS** → **Weekly Allowance Batches**
    - Should see empty list or existing batches
    - Try creating a new batch (draft mode)

4. **Check Salary Structures**:
    - Navigate to **HRIS** → **Salary Structures**
    - Open any salary structure
    - Should see new fields:
        - "Weekly Allowance Enabled" checkbox
        - "Weekly Allowance Rate" field
        - "Prorate Enabled" checkbox

5. **Check Payroll Records**:
    - Navigate to **HRIS** → **Payroll Records**
    - Open any record
    - Should see new fields:
        - Prorate information
        - Weekly allowance deductions

#### C. Test Database Queries

```bash
php artisan tinker
```

```php
// Test PayrollSetting model
$settings = \App\Models\Hris\PayrollSetting::all();
echo "Payroll Settings: " . $settings->count() . "\n";

// Test WeeklyAllowanceBatch model
$batches = \App\Models\Hris\WeeklyAllowanceBatch::all();
echo "Weekly Allowance Batches: " . $batches->count() . "\n";

// Test new fields in SalaryStructure
$structure = \App\Models\Hris\SalaryStructure::first();
if ($structure) {
    echo "Weekly Allowance Enabled: " . ($structure->weekly_allowance_enabled ? 'Yes' : 'No') . "\n";
    echo "Prorate Enabled: " . ($structure->prorate_enabled ? 'Yes' : 'No') . "\n";
}

exit
```

**Expected Output**: All queries should work without errors

#### D. Check Permissions

```bash
php artisan tinker
```

```php
// Check super_admin has new permissions
$role = Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
$permissions = $role->permissions->pluck('name')->filter(function($name) {
    return str_contains($name, 'weekly_allowance') || str_contains($name, 'payroll_setting');
});
echo "New permissions for super_admin: " . $permissions->count() . "\n";
$permissions->each(fn($p) => print("  - $p\n"));

exit
```

**Expected Output**: Should list permissions like:

- `view_weekly_allowance_batch`
- `create_weekly_allowance_batch`
- `view_payroll_setting`
- etc.

---

### Step 12: Monitor for Issues

**Time**: 15-30 minutes

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log

# In another terminal, watch Nginx logs
tail -f /var/log/nginx/error.log

# In another terminal, watch PHP-FPM logs
tail -f /var/log/php8.2-fpm.log
```

**What to monitor**:

- No 500 errors
- No database connection errors
- No permission errors
- Users can access new features

---

## 🎯 Feature Testing Checklist

### Test Weekly Allowance System

- [ ] **Create Batch**: HRIS → Weekly Allowance Batches → Create
    - Select week dates
    - System should auto-calculate eligible employees
    - Save as draft

- [ ] **Review Payments**: Open batch → View payments tab
    - Should show list of employees
    - Each payment shows: days worked, daily rate, total amount
    - Bank info warnings if incomplete

- [ ] **Workflow**: Test approval workflow
    - Submit to HR (draft → pending_hr)
    - Submit to Finance (pending_hr → pending_finance)
    - Approve by Finance (pending_finance → pending_final)
    - Final Approval (pending_final → approved)
    - Mark as Paid (approved → paid)

- [ ] **Export**: Try exporting batch to Excel
    - Should download file with all payment details

### Test Payroll Settings

- [ ] **View Settings**: HRIS → Payroll Settings
    - Should see BPJS rates, tax brackets, etc.
    - All values should be populated from seeder

- [ ] **Edit Setting**: Edit any setting
    - Change value
    - Save
    - Verify change persists

- [ ] **Filter by Category**: Use category filter
    - Filter by "BPJS"
    - Filter by "Tax"
    - Filter by "General"

### Test Enhanced Salary Structures

- [ ] **Edit Salary Structure**: HRIS → Salary Structures → Edit any
    - Enable "Weekly Allowance"
    - Set weekly allowance rate (e.g., 50000)
    - Enable "Prorate"
    - Save
    - Verify changes saved

### Test Payroll Calculation

- [ ] **Create Payrun**: HRIS → Payrun Batches → Create
    - Select period
    - Calculate payroll
    - Check payroll records
    - Verify prorate calculations if employee joined mid-period
    - Verify weekly allowance deductions

---

## 🔄 Rollback Plan (If Something Goes Wrong)

### Quick Rollback (5 minutes)

```bash
# 1. Put in maintenance mode
php artisan down

# 2. Restore database backup
mysql -u pm_user -p homefinder_pm < /var/backups/homefinder-pm/backup_YYYYMMDD_HHMMSS.sql

# 3. Revert to previous code
git log --oneline -10  # Find previous commit hash
git reset --hard <previous-commit-hash>

# 4. Reinstall dependencies
composer install --no-dev --optimize-autoloader

# 5. Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 6. Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Restart services
php artisan queue:restart
sudo systemctl restart php8.2-fpm nginx
sudo supervisorctl restart laravel-worker:*

# 8. Bring back online
php artisan up
```

---

## 📊 Database Changes Summary

### New Tables Created

1. **payroll_settings** - Stores BPJS rates, tax brackets, configuration
2. **weekly_allowance_batches** - Weekly allowance batch records
3. **weekly_allowance_payments** - Individual employee payments per batch

### Modified Tables

1. **payroll_records** - Added prorate and weekly allowance fields
2. **salary_structures** - Added weekly allowance and prorate options

### Total Migrations: 8

---

## 🔍 Troubleshooting Common Issues

### Issue 1: Migration Fails

**Error**: `SQLSTATE[42S01]: Base table or view already exists`

**Solution**:

```bash
# Check which migrations already ran
php artisan migrate:status

# If table exists but migration shows "Pending", manually mark as ran
php artisan tinker
```

```php
DB::table('migrations')->insert([
    'migration' => '2026_01_26_000001_create_weekly_allowance_batches_table',
    'batch' => DB::table('migrations')->max('batch') + 1
]);
exit
```

### Issue 2: Permissions Not Showing

**Error**: New menu items not visible

**Solution**:

```bash
# Regenerate permissions
php artisan shield:generate --all
php artisan shield:super-admin

# Clear permission cache
php artisan permission:cache-reset

# Clear browser cache (Ctrl+Shift+R)
```

### Issue 3: Seeder Fails

**Error**: `Class 'PayrollSettingSeeder' not found`

**Solution**:

```bash
# Regenerate autoload files
composer dump-autoload

# Try seeding again
php artisan db:seed --class=PayrollSettingSeeder
```

### Issue 4: 500 Error After Deployment

**Solution**:

```bash
# Check logs
tail -50 storage/logs/laravel.log

# Common fixes:
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Check file permissions
sudo chown -R www-data:www-data /var/www/homefinder-pm
sudo chmod -R 775 storage bootstrap/cache

# Restart services
sudo systemctl restart php8.2-fpm nginx
```

### Issue 5: Queue Not Processing

**Solution**:

```bash
# Check Supervisor status
sudo supervisorctl status

# Restart workers
sudo supervisorctl restart laravel-worker:*

# Check worker logs
tail -f storage/logs/worker.log
```

---

## 📞 Support Contacts

- **Developer**: [Your contact]
- **System Admin**: [Admin contact]
- **Emergency Rollback**: Follow rollback plan above

---

## ✅ Deployment Completion Checklist

- [ ] Database backup created
- [ ] Code deployed successfully
- [ ] All 8 migrations ran
- [ ] Payroll settings seeded
- [ ] Permissions generated
- [ ] Caches cleared and optimized
- [ ] Services restarted
- [ ] Application back online
- [ ] Logs checked (no errors)
- [ ] New features accessible in admin panel
- [ ] Weekly allowance batch creation tested
- [ ] Payroll settings accessible
- [ ] Salary structures show new fields
- [ ] Team notified of successful deployment
- [ ] Documentation updated

---

## 📝 Post-Deployment Notes

**Deployment Date**: ********\_********  
**Deployed By**: ********\_********  
**Deployment Duration**: ****\_**** minutes  
**Issues Encountered**: ********\_********  
**Resolution**: ********\_********



---

**Last Updated**: January 26, 2026  
**Version**: 1.0
