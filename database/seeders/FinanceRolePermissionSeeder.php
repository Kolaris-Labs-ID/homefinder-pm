<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Finance Center — roles & permissions (EXPENSE_MODULE.MD Phase 1).
 *
 * Segregation of duties: create (finance_creator) / approve (COO) /
 * pay (finance_executor) / reconcile (finance_reconciler) are never held by one role.
 * `reverse` and `manage_settings` are reserved to super_admin (via Gate::before).
 */
class FinanceRolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $permissions = [
            'expense_requests.view', 'expense_requests.view_all', 'expense_requests.create',
            'expense_requests.update_own_draft', 'expense_requests.submit', 'expense_requests.review',
            'expense_requests.verify', 'expense_requests.reject', 'expense_requests.request_revision',
            'expense_requests.cancel',
            'payment_requests.view', 'payment_requests.view_all', 'payment_requests.create',
            'payment_requests.submit_to_coo', 'payment_requests.approve_as_coo', 'payment_requests.reject_as_coo',
            'payment_requests.request_clarification', 'payment_requests.mark_paid', 'payment_requests.reconcile',
            'payment_requests.void', 'payment_requests.reverse',
            'finance_center.view', 'finance_center.view_sensitive_payment_data',
            'finance_center.view_payroll_placeholders', 'finance_center.manage_settings',
            'finance_attachments.upload', 'finance_attachments.download', 'finance_attachments.delete_own_unlocked',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $matrix = [
            'finance_reviewer' => [
                'expense_requests.view', 'expense_requests.view_all',
                'expense_requests.review', 'expense_requests.verify',
                'expense_requests.reject', 'expense_requests.request_revision',
                'finance_center.view', 'finance_center.view_payroll_placeholders',
                'finance_attachments.download',
            ],
            'finance_creator' => [
                'expense_requests.view', 'expense_requests.view_all',
                'payment_requests.view', 'payment_requests.view_all',
                'payment_requests.create', 'payment_requests.submit_to_coo',
                'payment_requests.void',
                'finance_center.view', 'finance_center.view_sensitive_payment_data',
                'finance_center.view_payroll_placeholders',
                'finance_attachments.download',
            ],
            'finance_executor' => [
                'payment_requests.view', 'payment_requests.view_all',
                'payment_requests.mark_paid',
                'finance_center.view', 'finance_center.view_sensitive_payment_data',
                'finance_center.view_payroll_placeholders',
                'finance_attachments.upload', 'finance_attachments.download',
                'finance_attachments.delete_own_unlocked',
            ],
            'finance_reconciler' => [
                'payment_requests.view', 'payment_requests.view_all',
                'payment_requests.reconcile',
                'finance_center.view', 'finance_center.view_sensitive_payment_data',
                'finance_center.view_payroll_placeholders',
                'finance_attachments.upload', 'finance_attachments.download',
            ],
            'COO' => [
                'payment_requests.view', 'payment_requests.view_all',
                'payment_requests.approve_as_coo', 'payment_requests.reject_as_coo',
                'payment_requests.request_clarification',
                'finance_center.view', 'finance_center.view_sensitive_payment_data',
            ],
        ];

        foreach ($matrix as $roleName => $grants) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => self::GUARD]);
            $role->givePermissionTo($grants);
        }

        if ($superAdmin = Role::where('name', 'super_admin')->where('guard_name', self::GUARD)->first()) {
            $superAdmin->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
