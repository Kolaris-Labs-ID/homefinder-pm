<?php

namespace App\Services\Hris;

use App\Models\Hris\JobPosting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ResumeStorageService
{
    public const DISK = 'private';

    public const MAX_BYTES = 1048576; // 1 MB

    /**
     * Validate and store an uploaded CV on the private disk.
     * Returns the resume_* metadata columns for the applicant.
     */
    public function store(UploadedFile $file, JobPosting $job): array
    {
        $this->validate($file);

        $content = (string) file_get_contents($file->getRealPath());
        $path = "recruitment/resumes/{$job->id}/".Str::uuid()->toString().'.pdf';

        Storage::disk(self::DISK)->put($path, $content);

        return [
            'resume_disk' => self::DISK,
            'resume_file_path' => $path,
            'resume_original_filename' => $file->getClientOriginalName(),
            'resume_mime_type' => 'application/pdf',
            'resume_size_bytes' => strlen($content),
            'resume_file_hash' => hash('sha256', $content),
            'resume_uploaded_at' => now(),
        ];
    }

    public function delete(?string $disk, ?string $path): void
    {
        if (blank($disk) || blank($path)) {
            return;
        }

        Storage::disk($disk)->delete($path);
    }

    /**
     * MVP CV policy: PDF only, ≤ 1 MB, content-validated (magic bytes), not extension-only.
     */
    protected function validate(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new \RuntimeException('Resume exceeds the 1 MB limit.');
        }

        $header = (string) file_get_contents($file->getRealPath(), false, null, 0, 5);
        if (! str_starts_with($header, '%PDF')) {
            throw new \RuntimeException('Resume must be a valid PDF file.');
        }
    }
}
