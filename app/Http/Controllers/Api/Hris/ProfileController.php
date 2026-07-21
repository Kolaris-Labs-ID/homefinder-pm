<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'employee.primaryPosition.department',
            'employee.primaryPosition.position',
            'employee.emergencyContacts',
        ]);

        abort_if(! $user->employee, 404, 'Employee profile not found.');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'division' => $user->division,
                'is_division_head' => $user->is_division_head,
                'mood_emoji' => $user->mood_emoji,
                'mood_text' => $user->mood_text,
                'roles' => $user->getRoleNames(),
            ],
            'employee' => $user->employee->only([
                'id',
                'nik',
                'date_of_birth',
                'place_of_birth',
                'gender',
                'marital_status',
                'address',
                'phone',
                'emergency_contact_name',
                'emergency_contact_phone',
                'bank_name',
                'bank_account_number',
                'bank_account_holder',
                'npwp_number',
                'bpjs_kesehatan_number',
                'bpjs_ketenagakerjaan_number',
                'hire_date',
                'employment_status',
                'is_active',
            ]) + [
                'current_position' => optional($user->employee->primaryPosition)->position?->title,
                'current_department' => optional($user->employee->primaryPosition)->department?->name,
                'emergency_contacts' => $user->employee->emergencyContacts->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'relationship' => $c->relationship,
                    'is_primary' => $c->is_primary,
                ])->values(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $employee = optional($request->user())->employee;

        abort_if(! $employee, 404, 'Employee profile not found.');

        $data = $request->validate([
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_holder' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:255'],
        ]);

        $employee->update(array_filter($data, fn ($value) => $value !== null));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'employee' => $employee->fresh(),
        ]);
    }

    public function updateMood(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'mood_emoji' => ['nullable', 'string', 'max:10'],
            'mood_text' => ['nullable', 'string', 'max:60'],
        ]);

        // Only touch what the caller actually sent: validate() omits absent nullable
        // keys, so an omitted field is left alone and an explicit null still clears it.
        $user->update($data);

        return response()->json([
            'message' => 'Mood updated successfully.',
            'mood_emoji' => $user->mood_emoji,
            'mood_text' => $user->mood_text,
        ]);
    }
}
