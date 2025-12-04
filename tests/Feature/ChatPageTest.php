<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('authenticated user can access chat page', function () {
    $role = Role::firstOrCreate(['name' => 'test-role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user);

    $response = $this->get('/admin/chat');

    expect($response->status())->toBeIn([200, 302]);
});

test('unauthenticated user is redirected to login when accessing chat page', function () {
    $response = $this->get('/admin/chat');

    // Should redirect to login
    expect($response->status())->toBe(302);
    expect($response->headers->get('Location'))->toContain('login');
});
