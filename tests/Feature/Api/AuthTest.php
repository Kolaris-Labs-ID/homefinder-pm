<?php

use App\Models\Hris\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

describe('Login endpoint', function () {
    it('logs in user with valid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'division',
                    'is_division_head',
                ],
                'employee',
            ])
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.email', 'test@example.com');

        expect($response->json('token'))->not->toBeNull();
    });

    it('logs in user with employee profile', function () {
        $employee = Employee::factory()->create();

        $response = postJson('/api/auth/login', [
            'email' => $employee->user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'employee' => [
                    'id',
                    'nik',
                    'hire_date',
                    'employment_status',
                    'is_active',
                ],
            ]);

        expect($response->json('employee.id'))->toBe($employee->id);
    });

    it('returns null employee for user without employee profile', function () {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonPath('employee', null);
    });

    it('rejects invalid credentials', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('rejects non-existent user', function () {
        $response = postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates required fields', function () {
        $response = postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    });

    it('validates email format', function () {
        $response = postJson('/api/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('accepts custom device name', function () {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'iPhone 15 Pro',
        ]);

        $response->assertOk();

        // Verify token was created with custom device name
        $token = $user->tokens()->latest()->first();
        expect($token->name)->toBe('iPhone 15 Pro');
    });

    it('uses default device name when not provided', function () {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $response = postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();

        // Verify token was created with default device name
        $token = $user->tokens()->latest()->first();
        expect($token->name)->toBe('mobile-app');
    });
});

describe('Logout endpoint', function () {
    it('logs out authenticated user', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');
    });

    it('requires authentication', function () {
        $response = postJson('/api/auth/logout');

        $response->assertUnauthorized();
    });

    it('revokes current token', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test-token');

        $response = postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $token->plainTextToken,
        ]);

        $response->assertOk();

        // Verify token was revoked
        expect($user->tokens()->count())->toBe(0);
    });

    it('only revokes current token, not all tokens', function () {
        $user = User::factory()->create();
        $token1 = $user->createToken('device-1');
        $token2 = $user->createToken('device-2');

        // Logout using token1
        $response = postJson('/api/auth/logout', [], [
            'Authorization' => 'Bearer ' . $token1->plainTextToken,
        ]);

        $response->assertOk();

        // Verify only token1 was revoked
        expect($user->tokens()->count())->toBe(1);
        expect($user->tokens()->first()->name)->toBe('device-2');
    });
});

describe('Me endpoint', function () {
    it('returns authenticated user info', function () {
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'division' => 'Engineering',
            'is_division_head' => true,
        ]);
        Sanctum::actingAs($user);

        $response = getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'division',
                    'is_division_head',
                ],
                'employee',
            ])
            ->assertJsonPath('user.name', 'John Doe')
            ->assertJsonPath('user.email', 'john@example.com')
            ->assertJsonPath('user.division', 'Engineering')
            ->assertJsonPath('user.is_division_head', true);
    });

    it('returns employee info when user has employee profile', function () {
        $employee = Employee::factory()->create([
            'nik' => 'EMP001',
            'employment_status' => 'permanent',
            'is_active' => true,
        ]);
        Sanctum::actingAs($employee->user);

        $response = getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'employee' => [
                    'id',
                    'nik',
                    'hire_date',
                    'employment_status',
                    'is_active',
                ],
            ])
            ->assertJsonPath('employee.nik', 'EMP001')
            ->assertJsonPath('employee.employment_status', 'permanent')
            ->assertJsonPath('employee.is_active', true);
    });

    it('returns null employee when user has no employee profile', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('employee', null);
    });

    it('requires authentication', function () {
        $response = getJson('/api/auth/me');

        $response->assertUnauthorized();
    });
});

describe('Auth flow integration', function () {
    it('completes full login-me-logout flow', function () {
        $user = User::factory()->create([
            'email' => 'flow@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Login
        $loginResponse = postJson('/api/auth/login', [
            'email' => 'flow@example.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertOk();
        $token = $loginResponse->json('token');

        // Verify token was created
        expect($user->tokens()->count())->toBe(1);

        // Get user info using withToken helper
        $meResponse = $this->withToken($token)->getJson('/api/auth/me');

        $meResponse->assertOk()
            ->assertJsonPath('user.email', 'flow@example.com');

        // Logout
        $logoutResponse = $this->withToken($token)->postJson('/api/auth/logout');

        $logoutResponse->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        // Verify token was revoked from database
        expect($user->fresh()->tokens()->count())->toBe(0);
    });

    it('allows multiple device logins', function () {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        // Login from device 1
        $response1 = postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'device-1',
        ]);

        $response1->assertOk();
        $token1 = $response1->json('token');

        // Login from device 2
        $response2 = postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
            'device_name' => 'device-2',
        ]);

        $response2->assertOk();
        $token2 = $response2->json('token');

        // Both tokens should work
        $me1 = getJson('/api/auth/me', ['Authorization' => 'Bearer ' . $token1]);
        $me2 = getJson('/api/auth/me', ['Authorization' => 'Bearer ' . $token2]);

        $me1->assertOk();
        $me2->assertOk();

        // User should have 2 tokens
        expect($user->tokens()->count())->toBe(2);
    });
});

describe('Google Sign-In endpoint', function () {
    it('logs in user with valid Google ID token and employee record', function () {
        // Create user with employee
        $employee = Employee::factory()->create([
            'nik' => 'EMP001',
        ]);
        $user = $employee->user;
        $user->update([
            'email' => 'test@example.com',
            'google_id' => '123456789',
        ]);

        // Mock Google token verification
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => '123456789',
                'email' => 'test@example.com',
                'email_verified' => 'true',
                'name' => 'Test User',
                'picture' => 'https://example.com/photo.jpg',
            ], 200),
        ]);

        $response = postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
            'device_name' => 'mobile-app',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'division',
                    'is_division_head',
                    'avatar_url',
                ],
                'employee' => [
                    'id',
                    'nik',
                    'hire_date',
                    'employment_status',
                    'is_active',
                ],
            ])
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonPath('employee.nik', 'EMP001');

        expect($response->json('token'))->not->toBeNull();
    });

    it('returns 404 when user has no employee record', function () {
        // Create user without employee
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'google_id' => '123456789',
        ]);

        // Mock Google token verification
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => '123456789',
                'email' => 'test@example.com',
                'email_verified' => 'true',
                'name' => 'Test User',
            ], 200),
        ]);

        $response = postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertNotFound()
            ->assertJsonPath('message', 'No employee account found for this Google account.');
    });

    it('returns 404 when Google account not found in system', function () {
        // Mock Google token verification for unknown user
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => '999999999',
                'email' => 'unknown@example.com',
                'email_verified' => 'true',
                'name' => 'Unknown User',
            ], 200),
        ]);

        $response = postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertNotFound()
            ->assertJsonPath('message', 'No employee account found for this Google account.');
    });

    it('returns 401 when Google token is invalid', function () {
        // Mock Google token verification failure
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'error' => 'invalid_token',
            ], 400),
        ]);

        $response = postJson('/api/auth/google', [
            'id_token' => 'invalid-google-token',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or expired Google token.');
    });

    it('links Google ID to existing user by email', function () {
        // Create user with employee but no google_id
        $employee = Employee::factory()->create();
        $user = $employee->user;
        $user->update([
            'email' => 'test@example.com',
            'google_id' => null,
        ]);

        // Mock Google token verification
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => '123456789',
                'email' => 'test@example.com',
                'email_verified' => 'true',
                'name' => 'Test User',
                'picture' => 'https://example.com/photo.jpg',
            ], 200),
        ]);

        $response = postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertOk();

        // Verify google_id was linked
        $user->refresh();
        expect($user->google_id)->toBe('123456789');
        expect($user->avatar_url)->toBe('https://example.com/photo.jpg');
    });

    it('validates required id_token field', function () {
        $response = postJson('/api/auth/google', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['id_token']);
    });

    it('accepts custom device name', function () {
        $employee = Employee::factory()->create();
        $user = $employee->user;
        $user->update([
            'email' => 'test@example.com',
            'google_id' => '123456789',
        ]);

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => '123456789',
                'email' => 'test@example.com',
                'email_verified' => 'true',
                'name' => 'Test User',
            ], 200),
        ]);

        $response = postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
            'device_name' => 'iPhone 15 Pro',
        ]);

        $response->assertOk();

        // Verify token was created with custom device name
        $token = $user->tokens()->latest()->first();
        expect($token->name)->toBe('iPhone 15 Pro');
    });

    it('uses default device name when not provided', function () {
        $employee = Employee::factory()->create();
        $user = $employee->user;
        $user->update([
            'email' => 'test@example.com',
            'google_id' => '123456789',
        ]);

        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response([
                'sub' => '123456789',
                'email' => 'test@example.com',
                'email_verified' => 'true',
                'name' => 'Test User',
            ], 200),
        ]);

        $response = postJson('/api/auth/google', [
            'id_token' => 'valid-google-token',
        ]);

        $response->assertOk();

        // Verify token was created with default device name
        $token = $user->tokens()->latest()->first();
        expect($token->name)->toBe('mobile-app');
    });
});
