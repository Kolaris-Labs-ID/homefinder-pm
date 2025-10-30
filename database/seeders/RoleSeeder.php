<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleSeeder extends Seeder
{
    public function run()
    {
        // Daftar resource Filament
        $resources = [
            'project',
            'ticket',
            'ticket_priority',
            'ticket_comment',
            'notification',
            'user',
        ];

        $actions = ['view', 'view_any', 'create', 'update', 'delete'];

        // Buat permission granular untuk setiap resource
        $permissions = [];
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $permissions[] = $action . '_' . $resource;
            }
        }

        // Insert permissions jika belum ada
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Buat role super_admin, admin, member
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $member = Role::firstOrCreate(['name' => 'member']);

        // super_admin: semua permission
        $superAdmin->syncPermissions(Permission::all());

        // admin: semua permission kecuali user delete
        $adminPermissions = Permission::whereNotIn('name', ['delete_user'])->get();
        $admin->syncPermissions($adminPermissions);

        // member: hanya view/view_any project, ticket, ticket_priority, ticket_comment, notification, dan update ticket (untuk drag & drop)
        $memberPermissions = Permission::where(function($q) {
            $q->whereIn('name', [
                'view_project', 'view_any_project',
                'view_ticket', 'view_any_ticket', 'update_ticket',
                'view_ticket_priority', 'view_any_ticket_priority',
                'view_ticket_comment', 'view_any_ticket_comment',
                'view_notification', 'view_any_notification',
            ]);
        })->get();
        $member->syncPermissions($memberPermissions);

        // Otomatis assign role member ke user baru (hanya contoh, implementasi production sebaiknya di observer User::created)
        // User::whereDoesntHave('roles')->update(['role_id' => $member->id]);
    }
}
