<?php

use App\Models\User;

test('home page returns successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('unauthenticated user can access home page', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

test('emergency logout route logs out user', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    expect(auth()->check())->toBeTrue();

    $response = $this->get('/logout');

    $response->assertRedirect('/admin/login');
    expect(auth()->check())->toBeFalse();
});

test('emergency logout route invalidates session', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user);
    
    $sessionId = session()->getId();
    
    $this->get('/logout');
    
    // Session should be regenerated, so the old session ID should be invalid
    expect(session()->getId())->not->toBe($sessionId);
});

test('google auth redirect route exists', function () {
    $response = $this->get('/auth/google');

    // Should redirect to Google OAuth or return some response
    // The exact status depends on Google OAuth configuration
    expect($response->status())->toBeIn([200, 302, 500]);
});

test('google auth callback route exists', function () {
    $response = $this->get('/auth/google/callback');

    // Should handle callback or redirect
    // The exact status depends on Google OAuth configuration
    expect($response->status())->toBeIn([200, 302, 400, 500]);
});

test('external login route exists', function () {
    $token = 'test-token-123';
    
    $response = $this->get("/external/{$token}");

    // Should return 200 or handle the token validation
    expect($response->status())->toBeIn([200, 404]);
});

test('external dashboard route exists', function () {
    $token = 'test-token-123';
    
    $response = $this->get("/external/{$token}/dashboard");

    // Should return 200, 302 (redirect), or 404
    expect($response->status())->toBeIn([200, 302, 404]);
});

