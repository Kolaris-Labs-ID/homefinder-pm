<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
}
