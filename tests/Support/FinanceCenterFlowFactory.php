<?php

namespace Tests\Support;

use App\Models\User;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Real-seeder user/role provisioning helpers for Finance Center tests.
 * Runs BOTH production seeders so finance_creator and finance_executor accumulate
 * permissions from both. Uses static methods only — no global helpers.
 */
final class FinanceCenterFlowFactory
{
    private const GUARD = 'web';

    private const REQUIRED_PERMISSIONS = [
        'expense_requests.verify',
        'payment_requests.approve',
        'payment_requests.create',
        'payment_requests.mark_paid',
        'payment_bundles.sign.finance',
        'payment_bundles.sign.executive',
        'payment_bundles.mark_paid',
    ];

    public static function seedFinanceRolesAndPermissions(): void
    {
        app(FinanceRolePermissionSeeder::class)->run();
        app(PaymentBundleRolePermissionSeeder::class)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function userWithRole(string $role, array $attributes = []): User
    {
        $realRole = Role::where('name', $role)->where('guard_name', self::GUARD)->firstOrFail();
        $user = User::factory()->create($attributes);
        $user->assignRole($realRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public static function assertRequiredPermissionsExist(): void
    {
        foreach (self::REQUIRED_PERMISSIONS as $perm) {
            \PHPUnit\Framework\Assert::assertTrue(
                Permission::where('name', $perm)->where('guard_name', self::GUARD)->exists(),
                "Required finance permission missing on web guard: {$perm}"
            );
        }
    }

    public static function financeReviewer(array $attributes = []): User
    {
        return self::userWithRole('finance_reviewer', $attributes);
    }

    public static function financeCreator(array $attributes = []): User
    {
        return self::userWithRole('finance_creator', $attributes);
    }

    public static function financeExecutor(array $attributes = []): User
    {
        return self::userWithRole('finance_executor', $attributes);
    }

    public static function financeReconciler(array $attributes = []): User
    {
        return self::userWithRole('finance_reconciler', $attributes);
    }

    public static function coo(array $attributes = []): User
    {
        return self::userWithRole('COO', $attributes);
    }

    public static function financeSigner(array $attributes = []): User
    {
        return self::userWithRole('finance_signer', $attributes);
    }

    public static function executiveSigner(array $attributes = []): User
    {
        return self::userWithRole('executive_signer', $attributes);
    }
}
