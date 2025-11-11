#!/bin/bash

echo "=== Create Super Admin User - Quick Method ==="
echo ""

cd /var/www/partnershub-pm

echo "Method 1: Using Laravel Tinker (Interactive)"
echo "----------------------------------------"
echo ""
echo "Run this command:"
echo "  php artisan tinker"
echo ""
echo "Then paste this code:"
echo ""
cat << 'EOF'
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

// Ensure roles exist
$seeder = new \Database\Seeders\RoleSeeder();
$seeder->run();

// Get or create roles
$superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
$panelUserRole = Role::firstOrCreate(['name' => 'panel_user', 'guard_name' => 'web']);

// Create super admin user (CHANGE EMAIL AND PASSWORD)
$user = User::firstOrCreate(
    ['email' => 'admin@partnershub.co'],  // ← Change this email
    [
        'name' => 'Super Admin',
        'password' => Hash::make('your-secure-password-here'),  // ← Change this password
    ]
);

// Assign roles
$user->assignRole($superAdminRole);
$user->assignRole($panelUserRole);

// Sync all permissions
$superAdminRole->syncPermissions(\Spatie\Permission\Models\Permission::all());

echo "Super admin created: " . $user->email;
EOF

echo ""
echo ""
echo "Method 2: Using PHP Script"
echo "----------------------------------------"
echo "Run: php create_super_admin.php"
echo ""
echo "Method 3: Using Filament Shield Command"
echo "----------------------------------------"
echo "Run: php artisan shield:super-admin"
echo ""


