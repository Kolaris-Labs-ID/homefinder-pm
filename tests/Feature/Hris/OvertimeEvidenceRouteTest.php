<?php

use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('returns overtime evidence image for authenticated users when file exists', function () {
    $user = User::factory()->create();
    $path = 'overtime-evidence/2026/03/user_1_log_1_test.jpg';
    Storage::disk('local')->put($path, 'fake-jpeg-bytes');

    $this->actingAs($user)
        ->get('/admin/overtime-evidence?'.http_build_query(['path' => $path]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertSee('fake-jpeg-bytes', false);
});

it('rejects unauthenticated users for overtime evidence', function () {
    $path = 'overtime-evidence/2026/03/x.jpg';
    Storage::disk('local')->put($path, 'x');

    $this->getJson('/admin/overtime-evidence?'.http_build_query(['path' => $path]))
        ->assertUnauthorized();
});

it('returns 404 when path prefix is not overtime-evidence', function () {
    $user = User::factory()->create();
    Storage::disk('local')->put('attendance-photos/x.jpg', 'x');

    $this->actingAs($user)
        ->get('/admin/overtime-evidence?'.http_build_query(['path' => 'attendance-photos/x.jpg']))
        ->assertNotFound();
});

it('returns 404 when file does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/overtime-evidence?'.http_build_query(['path' => 'overtime-evidence/2026/03/missing.jpg']))
        ->assertNotFound();
});
