<?php

namespace App\Http\Controllers\Api\Hris;

use App\Http\Controllers\Controller;
use App\Models\Hris\FinanceAttachment;
use App\Services\Hris\FinanceAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FinanceAttachmentController extends Controller
{
    public function __construct(
        protected FinanceAttachmentService $service
    ) {}

    /**
     * Stream a protected attachment after authorization. No public URLs exist.
     */
    public function download(Request $request, FinanceAttachment $financeAttachment)
    {
        $this->authorize('download', $financeAttachment);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($financeAttachment->file_path), 404);

        return $disk->download($financeAttachment->file_path, $financeAttachment->original_filename);
    }

    public function destroy(Request $request, FinanceAttachment $financeAttachment)
    {
        $this->authorize('delete', $financeAttachment);

        try {
            $this->service->delete($financeAttachment);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Attachment deleted']);
    }
}
