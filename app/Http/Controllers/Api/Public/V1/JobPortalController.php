<?php

namespace App\Http\Controllers\Api\Public\V1;

use App\Http\Controllers\Controller;
use App\Models\Hris\HiringCompany;
use App\Models\Hris\JobPosting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class JobPortalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $jobs = $this->filteredQuery($request)
            ->orderByDesc('published_public_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->paginatedJobs($jobs);
    }

    public function show(string $slug): JsonResponse
    {
        $job = JobPosting::publiclyVisible()->with('hiringCompany')->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $this->jobDetail($job)]);
    }

    public function company(string $companySlug): JsonResponse
    {
        $company = HiringCompany::active()->where('slug', $companySlug)->firstOrFail();

        return response()->json(['data' => $this->companyProfile($company)]);
    }

    public function companyJobs(string $companySlug, Request $request): JsonResponse
    {
        $company = HiringCompany::active()->where('slug', $companySlug)->firstOrFail();

        $jobs = $this->filteredQuery($request)
            ->where('hiring_company_id', $company->id)
            ->orderByDesc('published_public_at')
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return $this->paginatedJobs($jobs);
    }

    private function filteredQuery(Request $request)
    {
        return JobPosting::publiclyVisible()
            ->with('hiringCompany')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($w) => $w->where('public_title', 'like', $term)->orWhere('title', 'like', $term));
            })
            ->when($request->filled('location'), fn ($q) => $q->where('location', 'like', '%'.$request->string('location').'%'))
            ->when($request->filled('employment_type'), fn ($q) => $q->where('employment_type', $request->string('employment_type')))
            ->when($request->filled('work_arrangement'), fn ($q) => $q->where('work_arrangement', $request->string('work_arrangement')))
            ->when($request->filled('company'), fn ($q) => $q->whereHas('hiringCompany', fn ($c) => $c->where('slug', $request->string('company'))));
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 12), 1), 50);
    }

    private function paginatedJobs(LengthAwarePaginator $jobs): JsonResponse
    {
        return response()->json([
            'data' => collect($jobs->items())->map(fn (JobPosting $j) => $this->jobListItem($j))->all(),
            'meta' => [
                'current_page' => $jobs->currentPage(),
                'last_page' => $jobs->lastPage(),
                'per_page' => $jobs->perPage(),
                'total' => $jobs->total(),
            ],
        ]);
    }

    private function jobListItem(JobPosting $job): array
    {
        return array_merge([
            'slug' => $job->slug,
            'title' => $job->public_title ?: $job->title,
            'location' => $job->location,
            'work_arrangement' => $job->work_arrangement,
            'employment_type' => $job->employment_type,
            'posted_at' => optional($job->published_public_at)->toIso8601String(),
            'application_deadline' => optional($job->application_deadline)->toDateString(),
            'company' => $this->companyBrief($job->hiringCompany),
        ], $job->publicSalary());
    }

    private function jobDetail(JobPosting $job): array
    {
        return array_merge($this->jobListItem($job), [
            'description' => $job->public_description ?: $job->description,
            'requirements' => $job->public_requirements ?: $job->requirements,
            'benefits' => $job->public_benefits,
            'company' => $job->hiringCompany ? $this->companyProfile($job->hiringCompany) : null,
        ]);
    }

    private function companyBrief(?HiringCompany $company): ?array
    {
        if (! $company) {
            return null;
        }

        return [
            'slug' => $company->slug,
            'name' => $company->name,
            'logo_url' => $this->logoUrl($company),
        ];
    }

    private function companyProfile(HiringCompany $company): array
    {
        return [
            'slug' => $company->slug,
            'name' => $company->name,
            'logo_url' => $this->logoUrl($company),
            'website' => $company->website,
            'industry' => $company->industry,
            'location' => $company->location,
            'description' => $company->description,
        ];
    }

    private function logoUrl(HiringCompany $company): ?string
    {
        if (blank($company->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($company->logo_path);
    }
}
