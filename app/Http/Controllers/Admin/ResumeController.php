<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Hris\Applicant;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResumeController extends Controller
{
    /**
     * Stream the résumé inline (PDF preview). HR only — never a public URL.
     */
    public function view(Applicant $applicant): StreamedResponse
    {
        $this->authorize('viewResume', $applicant);

        return $this->stream($applicant, inline: true);
    }

    /**
     * Force-download the résumé as an attachment. HR only.
     */
    public function download(Applicant $applicant): StreamedResponse
    {
        $this->authorize('downloadResume', $applicant);

        return $this->stream($applicant, inline: false);
    }

    private function stream(Applicant $applicant, bool $inline): StreamedResponse
    {
        abort_if(blank($applicant->resume_file_path), 404);

        $disk = Storage::disk($applicant->resume_disk ?: 'private');
        abort_unless($disk->exists($applicant->resume_file_path), 404);

        $name = $applicant->resume_original_filename ?: 'resume.pdf';
        $disposition = $inline ? 'inline' : 'attachment';

        return $disk->response($applicant->resume_file_path, $name, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($name).'"',
        ]);
    }
}
