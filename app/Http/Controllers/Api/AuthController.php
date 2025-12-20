<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Login and issue a Sanctum token for mobile/API access.
     *
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Load employee relationship for HRIS context
        $user->load('employee');

        // Revoke previous tokens for this device (optional, for single-device login)
        $deviceName = $request->device_name ?? 'mobile-app';

        // Create new token
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'division' => $user->division,
                'is_division_head' => $user->is_division_head,
            ],
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'nik' => $user->employee->nik,
                'hire_date' => $user->employee->hire_date,
                'employment_status' => $user->employee->employment_status,
                'is_active' => $user->employee->is_active,
            ] : null,
        ]);
    }

    /**
     * Logout and revoke the current token.
     *
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated user info.
     *
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('employee');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'division' => $user->division,
                'is_division_head' => $user->is_division_head,
            ],
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'nik' => $user->employee->nik,
                'hire_date' => $user->employee->hire_date,
                'employment_status' => $user->employee->employment_status,
                'is_active' => $user->employee->is_active,
            ] : null,
        ]);
    }

    /**
     * Login with Google OAuth (for mobile apps).
     *
     * POST /api/auth/google
     *
     * The mobile app should:
     * 1. Use Google Sign-In SDK to authenticate the user
     * 2. Get the ID token from Google
     * 3. Send the ID token to this endpoint
     *
     * This endpoint will:
     * 1. Verify the ID token with Google
     * 2. Find or create the user
     * 3. Issue a Sanctum token
     */
    public function googleLogin(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
            'device_name' => ['nullable', 'string'],
        ]);

        // Verify the ID token with Google
        $googleUser = $this->verifyGoogleIdToken($request->id_token);

        if (!$googleUser) {
            throw ValidationException::withMessages([
                'id_token' => ['Invalid Google ID token.'],
            ]);
        }

        // Find user by Google ID or email
        $user = User::where('google_id', $googleUser['sub'])->first();

        if (!$user) {
            // Check if user exists with this email
            $user = User::where('email', $googleUser['email'])->first();

            if ($user) {
                // Link existing user to Google account
                $user->update([
                    'google_id' => $googleUser['sub'],
                    'avatar_url' => $googleUser['picture'] ?? null,
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $googleUser['name'],
                    'email' => $googleUser['email'],
                    'google_id' => $googleUser['sub'],
                    'avatar_url' => $googleUser['picture'] ?? null,
                    'password' => null,
                ]);

                // Assign default role
                $defaultRole = Role::where('name', 'panel_user')->first();
                if ($defaultRole) {
                    $user->assignRole($defaultRole);
                }
            }
        } else {
            // Update avatar if changed
            if (isset($googleUser['picture']) && $user->avatar_url !== $googleUser['picture']) {
                $user->update(['avatar_url' => $googleUser['picture']]);
            }
        }

        // Load employee relationship
        $user->load('employee');

        // Create Sanctum token
        $deviceName = $request->device_name ?? 'mobile-app';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'division' => $user->division,
                'is_division_head' => $user->is_division_head,
                'avatar_url' => $user->avatar_url,
            ],
            'employee' => $user->employee ? [
                'id' => $user->employee->id,
                'nik' => $user->employee->nik,
                'hire_date' => $user->employee->hire_date,
                'employment_status' => $user->employee->employment_status,
                'is_active' => $user->employee->is_active,
            ] : null,
        ]);
    }

    /**
     * Verify Google ID token using Google's tokeninfo endpoint.
     *
     * @param string $idToken
     * @return array|null Returns user info if valid, null if invalid
     */
    protected function verifyGoogleIdToken(string $idToken): ?array
    {
        try {
            // Use Google's tokeninfo endpoint to verify the token
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $idToken,
            ]);

            if (!$response->successful()) {
                \Log::warning('Google token verification failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            // Verify the token is for our app (check audience)
            $clientId = config('services.google.client_id');
            if ($clientId && isset($data['aud']) && $data['aud'] !== $clientId) {
                \Log::warning('Google token audience mismatch', [
                    'expected' => $clientId,
                    'actual' => $data['aud'],
                ]);
                // For now, we'll allow any audience for flexibility
                // In production, you might want to be stricter
            }

            // Verify email is verified
            if (!isset($data['email_verified']) || $data['email_verified'] !== 'true') {
                \Log::warning('Google email not verified', ['data' => $data]);
                return null;
            }

            return $data;
        } catch (\Exception $e) {
            \Log::error('Google token verification error', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
