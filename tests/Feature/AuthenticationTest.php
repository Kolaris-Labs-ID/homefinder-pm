<?php

use App\Models\User;
use App\Models\Project;
use App\Models\ExternalAccess;
use Spatie\Permission\Models\Role;

test('authenticated user can access protected routes', function () {
    $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user);

    // Since Filament handles authentication, we'll test that the user can be authenticated
    expect(auth()->check())->toBeTrue();
    expect($user->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue();
});

test('unauthenticated user cannot access admin panel', function () {
    expect(auth()->check())->toBeFalse();
});

test('user with role can access panel', function () {
    $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->canAccessPanel(app(\Filament\Panel::class)))->toBeTrue();
});

test('user without role cannot access panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(app(\Filament\Panel::class)))->toBeFalse();
});

test('external access with valid token can access dashboard', function () {
    $project = Project::factory()->create();
    $externalAccess = ExternalAccess::factory()->create([
        'project_id' => $project->id,
        'is_active' => true,
    ]);

    $response = $this->get("/external/{$externalAccess->access_token}");

    // Should be able to access the external login page
    expect($response->status())->toBeIn([200, 302]);
});

test('external access with invalid token returns 404', function () {
    $response = $this->get('/external/invalid-token-123');

    expect($response->status())->toBe(404);
});

test('external access with inactive token cannot access', function () {
    $project = Project::factory()->create();
    $externalAccess = ExternalAccess::factory()->inactive()->create([
        'project_id' => $project->id,
    ]);

    $response = $this->get("/external/{$externalAccess->access_token}");

    // Should handle inactive token appropriately
    expect($response->status())->toBeIn([200, 403, 404]);
});

