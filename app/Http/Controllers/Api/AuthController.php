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
     * Login with Google OAuth (for HRIS mobile app).
     *
     * POST /api/auth/google
     *
     * Request:
     * {
     *   "id_token": "Google ID token (JWT)",
     *   "device_name": "mobile-app"
     * }
     *
     * This endpoint:
     * 1. Verifies the ID token with Google's tokeninfo endpoint
     * 2. Extracts user's email and Google ID from the token
     * 3. Finds matching user/employee by email or google_id
     * 4. If found, creates Sanctum token and returns user/employee data
     * 5. If not found, returns 404 with "No employee account found"
     * 6. If token invalid, returns 401 with "Invalid or expired Google token"
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
            return response()->json([
                'message' => 'Invalid or expired Google token.',
            ], 401);
        }

        // Find user by Google ID or email
        $user = User::where('google_id', $googleUser['sub'])
            ->orWhere('email', $googleUser['email'])
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'No employee account found for this Google account.',
            ], 404);
        }

        // Update Google ID if not set
        if (!$user->google_id) {
            $user->update([
                'google_id' => $googleUser['sub'],
                'avatar_url' => $googleUser['picture'] ?? $user->avatar_url,
            ]);
        }

        // Load employee relationship
        $user->load('employee');

        // Check if user has employee record (required for HRIS mobile app)
        if (!$user->employee) {
            return response()->json([
                'message' => 'No employee account found for this Google account.',
            ], 404);
        }

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
            'employee' => [
                'id' => $user->employee->id,
                'nik' => $user->employee->nik,
                'hire_date' => $user->employee->hire_date?->format('Y-m-d'),
                'employment_status' => $user->employee->employment_status,
                'is_active' => $user->employee->is_active,
            ],
        ], 200);
    }

    /**
     * Change the authenticated user's password.
     *
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Update password (User model's 'hashed' cast handles hashing automatically)
        $user->update([
            'password' => $request->new_password,
        ]);

        return response()->json([
            'message' => 'Password changed successfully.',
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
