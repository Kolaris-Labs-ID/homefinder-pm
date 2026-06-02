<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Payment Bundle — roles & permissions (Phase 2).
 *
 * Sequential signing: finance_signer_1 → finance_signer_2 → executive_signer.
 * Uses givePermissionTo (additive) to avoid wiping grants from FinanceRolePermissionSeeder.
 */
class PaymentBundleRolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        $permissions = [
            'payment_bundles.view',
            'payment_bundles.create',
            'payment_bundles.update_draft',
            'payment_bundles.submit_for_signature',
            'payment_bundles.sign.finance',
            'payment_bundles.sign.executive',
            'payment_bundles.reject_signature',
            'payment_bundles.mark_paid',
            'payment_bundles.reject_payment_proof',
            'payment_bundles.void',
            'payment_bundles.cancel',
            'payment_bundles.download_pdf',
            'payment_bundles.manage_signers',
            'payment_bundles.verify_document',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        $matrix = [
            'finance_creator' => [
                'payment_bundles.view',
                'payment_bundles.create',
                'payment_bundles.update_draft',
                'payment_bundles.submit_for_signature',
                'payment_bundles.manage_signers',
                'payment_bundles.cancel',
                'payment_bundles.void',
                'payment_bundles.download_pdf',
                'payment_bundles.verify_document',
            ],
            'finance_signer' => [
                'payment_bundles.view',
                'payment_bundles.sign.finance',
                'payment_bundles.reject_signature',
                'payment_bundles.download_pdf',
                'payment_bundles.verify_document',
            ],
            'executive_signer' => [
                'payment_bundles.view',
                'payment_bundles.sign.executive',
                'payment_bundles.reject_signature',
                'payment_bundles.download_pdf',
                'payment_bundles.verify_document',
            ],
            'finance_executor' => [
                'payment_bundles.view',
                'payment_bundles.mark_paid',
                'payment_bundles.reject_payment_proof',
                'payment_bundles.download_pdf',
                'payment_bundles.verify_document',
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
