#!/bin/bash

# HRIS Permissions Setup Script
# This script generates and assigns all permissions for Phase 2, 3, and 4 HRIS features

echo "=========================================="
echo "HRIS Permissions Setup"
echo "=========================================="
echo ""

# Step 1: Generate all Shield permissions
echo "Step 1: Generating Shield permissions for all resources..."
php artisan shield:generate --all

if [ $? -eq 0 ]; then
    echo "✓ Permissions generated successfully"
else
    echo "✗ Failed to generate permissions"
    exit 1
fi

echo ""

# Step 2: Assign all permissions to super_admin role
echo "Step 2: Assigning all permissions to super_admin role..."
php artisan shield:super-admin --user=1

if [ $? -eq 0 ]; then
    echo "✓ Permissions assigned to super_admin"
else
    echo "✗ Failed to assign permissions"
    exit 1
fi

echo ""

# Step 3: Clear all caches
echo "Step 3: Clearing caches..."
php artisan permission:cache-reset
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "✓ Caches cleared"
echo ""

# Step 4: List all HRIS-related permissions
echo "Step 4: Verifying HRIS permissions..."
php artisan tinker --execute="
\$permissions = Spatie\Permission\Models\Permission::where('name', 'like', '%attendance%')
    ->orWhere('name', 'like', '%salary%')
    ->orWhere('name', 'like', '%payrun%')
    ->orWhere('name', 'like', '%reimbursement%')
    ->orWhere('name', 'like', '%job%')
    ->orWhere('name', 'like', '%applicant%')
    ->orWhere('name', 'like', '%interview%')
    ->orWhere('name', 'like', '%performance%')
    ->orWhere('name', 'like', '%training%')
    ->get();
echo 'Found ' . \$permissions->count() . ' HRIS permissions';
"

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Log out of Filament"
echo "2. Log back in"
echo "3. You should now see:"
echo "   - Attendance Logs (under HRIS)"
echo "   - Payroll menu group (Salary Structures, Payrun Batches, Reimbursements)"
echo "   - Recruitment menu group (Job Postings, Applicants, Interviews)"
echo "   - Performance menu group (Cycles, Goals, Reviews)"
echo "   - Training menu group (Training Records)"
echo ""
