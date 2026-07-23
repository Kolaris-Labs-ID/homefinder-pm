<?php

use App\Models\Hris\Employee;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->employee = Employee::factory()->create([
        'user_id' => $this->user->id,
        'is_active' => true,
    ]);
    Sanctum::actingAs($this->user);
});

it('rejects check-in without GPS coordinates', function () {
    $response = postJson('/api/hris/attendance/check-in', [
        'mode' => 'office',
        'source' => 'mobile',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('rejects check-in with latitude but no longitude', function () {
    $response = postJson('/api/hris/attendance/check-in', [
        'latitude' => -6.2088,
        'mode' => 'office',
        'source' => 'mobile',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['longitude']);
});

it('accepts check-in with GPS coordinates', function () {
    $response = postJson('/api/hris/attendance/check-in', [
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'mode' => 'office',
        'source' => 'mobile',
    ]);

    $response->assertStatus(201);
});

it('rejects check-out without GPS coordinates', function () {
    postJson('/api/hris/attendance/check-in', [
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'mode' => 'office',
        'source' => 'mobile',
    ])->assertStatus(201);

    $response = postJson('/api/hris/attendance/check-out', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('accepts check-out with GPS coordinates', function () {
    postJson('/api/hris/attendance/check-in', [
        'latitude' => -6.2088,
        'longitude' => 106.8456,
        'mode' => 'office',
        'source' => 'mobile',
    ])->assertStatus(201);

    $response = postJson('/api/hris/attendance/check-out', [
        'latitude' => -6.2088,
        'longitude' => 106.8456,
    ]);

    $response->assertStatus(200);
});
