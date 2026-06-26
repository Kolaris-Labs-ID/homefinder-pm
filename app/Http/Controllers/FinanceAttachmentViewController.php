<?php

namespace App\Http\Controllers;

use App\Models\Hris\FinanceAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceAttachmentViewController extends Controller
{
    /**
     * Stream finance attachments for authenticated admin users.
     * Files stay on the private disk; unsafe MIME types are forced to download.
     */
    public function view(FinanceAttachment $financeAttachment): StreamedResponse
    {
        $this->authorize('download', $financeAttachment);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($financeAttachment->file_path), 404);

        $mime = $financeAttachment->mime_type
            ?: $disk->mimeType($financeAttachment->file_path)
            ?: 'application/octet-stream';
        $inlineSafe = in_array($mime, ['application/pdf', 'image/png', 'image/jpeg'], true);

        return $disk->response(
            $financeAttachment->file_path,
            $financeAttachment->original_filename ?: basename($financeAttachment->file_path),
            [
                'Content-Type' => $inlineSafe ? $mime : 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            $inlineSafe ? 'inline' : 'attachment',
        );
    }
}
