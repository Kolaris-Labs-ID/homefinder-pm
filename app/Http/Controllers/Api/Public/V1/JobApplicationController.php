<?php

namespace App\Http\Controllers\Api\Public\V1;

use App\Exceptions\DuplicateApplicationException;
use App\Http\Controllers\Controller;
use App\Models\Hris\JobPosting;
use App\Services\Hris\JobApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class JobApplicationController extends Controller
{
    public function __construct(private JobApplicationService $service) {}

    public function store(Request $request, string $slug): JsonResponse
    {
        $job = JobPosting::publiclyVisible()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'cover_letter' => 'nullable|string|max:5000',
            'linkedin_url' => 'nullable|url|max:255',
            'portfolio_url' => 'nullable|url|max:255',
            'expected_salary' => 'nullable|numeric|min:0',
            'available_start_date' => 'nullable|date',
            'consent_privacy_policy' => 'accepted',
            'consent_data_processing' => 'accepted',
            'website' => 'prohibited', // honeypot: must be missing/empty
            'resume' => 'required|file|max:1024', // 1 MB
        ]);

        $this->assertPdf($request);

        try {
            $applicant = $this->service->apply(
                $job,
                $validated,
                $request->file('resume'),
                $request->ip(),
                $request->userAgent(),
            );
        } catch (DuplicateApplicationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'application_reference_code' => $applicant->application_reference_code,
            ],
            'message' => 'Application submitted successfully.',
        ], 201);
    }

    /**
     * Content-level PDF check (magic bytes), not extension-only.
     */
    private function assertPdf(Request $request): void
    {
        $header = (string) file_get_contents($request->file('resume')->getRealPath(), false, null, 0, 5);

        if (! str_starts_with($header, '%PDF')) {
            throw ValidationException::withMessages([
                'resume' => 'Resume must be a valid PDF file.',
            ]);
        }
    }
}
