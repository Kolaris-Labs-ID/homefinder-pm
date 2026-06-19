<?php

namespace App\Http\Controllers;

use App\Models\Hris\LeaveRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveAttachmentController extends Controller
{
    /**
     * Stream a leave request's attachment (e.g. sick-leave doctor letter) inline for approvers.
     * Policy-gated, served from the private disk — never a public URL.
     */
    public function view(LeaveRequest $leaveRequest): StreamedResponse
    {
        $this->authorize('view', $leaveRequest);

        abort_if(blank($leaveRequest->attachment_path), 404);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($leaveRequest->attachment_path), 404);

        $name = basename($leaveRequest->attachment_path);
        $mime = $disk->mimeType($leaveRequest->attachment_path) ?: 'application/octet-stream';

        // Only render known-safe types inline; anything else (e.g. a smuggled HTML/SVG) is
        // forced to download as opaque bytes so the admin origin can never execute it.
        $inlineSafe = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true);

        return $disk->response($leaveRequest->attachment_path, $name, [
            'Content-Type' => $inlineSafe ? $mime : 'application/octet-stream',
            'Content-Disposition' => ($inlineSafe ? 'inline' : 'attachment').'; filename="'.addslashes($name).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
