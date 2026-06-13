<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Internal project-document permissions (web guard). Access is project-scoped in the policy
 * (member or C-level) AND gated on these permissions. External dashboard access gets none.
 */
class ProjectDocumentPermissionSeeder extends Seeder
{
    private const GUARD = 'web';

    public const VIEW = 'view_project_document';

    public const CREATE = 'create_project_document';

    public const DOWNLOAD = 'download_project_document';

    public const DELETE = 'delete_project_document';

    public function run(): void
    {
        $all = [self::VIEW, self::CREATE, self::DOWNLOAD, self::DELETE];

        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        foreach (['super_admin', 'CEO', 'COO', 'CTO', 'manager', 'head-division'] as $role) {
            $this->grant($role, $all);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function grant(string $role, array $permissions): void
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => self::GUARD])
            ->givePermissionTo($permissions);
    }
}
