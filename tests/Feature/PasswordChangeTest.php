<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

test('user can change password via API and login with new password', function () {
    $originalPassword = 'original-password-123';
    $newPassword = 'new-password-456';

    $user = User::factory()->create([
        'password' => $originalPassword,
    ]);

    // Verify original password works
    expect(Hash::check($originalPassword, $user->fresh()->password))->toBeTrue();

    // Authenticate and change password via API
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/auth/change-password', [
        'current_password' => $originalPassword,
        'new_password' => $newPassword,
        'new_password_confirmation' => $newPassword,
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Password changed successfully.']);

    // Verify NEW password works (not double-hashed)
    $user->refresh();
    expect(Hash::check($newPassword, $user->password))->toBeTrue();

    // Verify OLD password no longer works
    expect(Hash::check($originalPassword, $user->password))->toBeFalse();
});

test('user can login with changed password', function () {
    $originalPassword = 'original-password-123';
    $newPassword = 'new-password-456';

    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => $originalPassword,
    ]);

    // Change password via API
    Sanctum::actingAs($user);

    $this->postJson('/api/auth/change-password', [
        'current_password' => $originalPassword,
        'new_password' => $newPassword,
        'new_password_confirmation' => $newPassword,
    ])->assertOk();

    // Revoke all tokens to simulate logout
    $user->tokens()->delete();

    // Try to login with NEW password (fresh request without auth)
    $loginResponse = $this->withoutMiddleware(\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class)
        ->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => $newPassword,
            'device_name' => 'test-device',
        ]);

    $loginResponse->assertOk()
        ->assertJsonStructure(['token', 'user', 'message']);
});

test('successful password change revokes other API tokens but keeps the current session', function () {
    $originalPassword = 'original-password-123';
    $newPassword = 'new-password-456';

    $user = User::factory()->create([
        'password' => $originalPassword,
    ]);

    $currentToken = $user->createToken('current-device');
    $otherToken = $user->createToken('other-device');
    $unrelatedUser = User::factory()->create();
    $unrelatedToken = $unrelatedUser->createToken('unrelated-device');

    $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/auth/change-password', [
            'current_password' => $originalPassword,
            'new_password' => $newPassword,
            'new_password_confirmation' => $newPassword,
        ])
        ->assertOk();

    expect($user->tokens()->pluck('id')->all())
        ->toBe([$currentToken->accessToken->id]);

    app('auth')->forgetGuards();
    $this->withToken($currentToken->plainTextToken)
        ->getJson('/api/auth/me')
        ->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($otherToken->plainTextToken)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    app('auth')->forgetGuards();
    $this->withToken($unrelatedToken->plainTextToken)
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.id', $unrelatedUser->id);
});

test('password is not double-hashed when saved', function () {
    $plainPassword = 'test-password-123';

    $user = User::factory()->create([
        'password' => $plainPassword,
    ]);

    $storedHash = $user->password;

    // The stored hash should be a valid bcrypt hash
    expect($storedHash)->toStartWith('$2y$');

    // Hash::check with plain password should return true
    expect(Hash::check($plainPassword, $storedHash))->toBeTrue();

    // If it were double-hashed, this would fail
    // because the stored value would be hash(hash(password))
    // and Hash::check(password, hash(hash(password))) would return false
});

test('user cannot change password with wrong current password', function () {
    $user = User::factory()->create([
        'password' => 'correct-password',
    ]);

    $currentToken = $user->createToken('current-device');
    $otherToken = $user->createToken('other-device');

    $response = $this->withToken($currentToken->plainTextToken)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'wrong-password',
            'new_password' => 'new-password-123',
            'new_password_confirmation' => 'new-password-123',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password']);

    expect($user->tokens()->pluck('id')->all())->toBe([
        $currentToken->accessToken->id,
        $otherToken->accessToken->id,
    ]);
});

test('password update via model attribute assignment works correctly', function () {
    $user = User::factory()->create();
    $newPassword = 'direct-assignment-password';

    // This simulates what EditProfile::setMobilePassword() does
    $user->password = $newPassword;
    $user->save();

    $user->refresh();

    // Password should be properly hashed and verifiable
    expect(Hash::check($newPassword, $user->password))->toBeTrue();
});

test('password update via model update method works correctly', function () {
    $user = User::factory()->create();
    $newPassword = 'update-method-password';

    // This simulates what AuthController::changePassword() does
    $user->update([
        'password' => $newPassword,
    ]);

    $user->refresh();

    // Password should be properly hashed and verifiable
    expect(Hash::check($newPassword, $user->password))->toBeTrue();
});
