<?php

namespace App\Exports;

use App\Models\Hris\Applicant;
use App\Models\Hris\JobPosting;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecruitmentReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected Carbon $startDate;
    protected Carbon $endDate;

    /**
     * Export recruitment report including job postings, applicant counts, and time-to-hire.
     * Requirements: 9.4
     */
    public function __construct(Carbon $startDate, Carbon $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function collection(): Collection
    {
        return JobPosting::query()
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->with(['department', 'applicants'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Job Posting ID',
            'Title',
            'Department',
            'Location',
            'Employment Type',
            'Status',
            'Published At',
            'Closed At',
            'Total Applicants',
            'Applied',
            'Shortlisted',
            'Interviewed',
            'Offered',
            'Hired',
            'Rejected',
            'Avg Time-to-Hire (Days)',
        ];
    }

    public function map($jobPosting): array
    {
        $applicants = $jobPosting->applicants;

        // Count applicants by status
        $statusCounts = $applicants->groupBy('status')->map->count();

        // Calculate average time-to-hire for hired applicants
        $hiredApplicants = $applicants->where('status', Applicant::STATUS_HIRED);
        $avgTimeToHire = 0;

        if ($hiredApplicants->count() > 0) {
            $totalDays = $hiredApplicants->sum(function ($applicant) {
                return $applicant->created_at->diffInDays($applicant->updated_at);
            });
            $avgTimeToHire = round($totalDays / $hiredApplicants->count(), 1);
        }

        return [
            $jobPosting->id,
            $jobPosting->title,
            $jobPosting->department?->name ?? '',
            $jobPosting->location ?? '',
            ucfirst(str_replace('_', ' ', $jobPosting->employment_type ?? '')),
            ucfirst($jobPosting->status ?? ''),
            $jobPosting->published_at?->format('Y-m-d H:i') ?? '',
            $jobPosting->closed_at?->format('Y-m-d H:i') ?? '',
            $applicants->count(),
            $statusCounts[Applicant::STATUS_APPLIED] ?? 0,
            $statusCounts[Applicant::STATUS_SHORTLISTED] ?? 0,
            $statusCounts[Applicant::STATUS_INTERVIEWED] ?? 0,
            $statusCounts[Applicant::STATUS_OFFERED] ?? 0,
            $statusCounts[Applicant::STATUS_HIRED] ?? 0,
            $statusCounts[Applicant::STATUS_REJECTED] ?? 0,
            $avgTimeToHire ?: '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1565C0'],
                ],
            ],
        ];
    }
}
