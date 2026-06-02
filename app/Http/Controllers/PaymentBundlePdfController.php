<?php

namespace App\Http\Controllers;

use App\Models\Hris\PaymentBundle;
use Illuminate\Support\Facades\Storage;

class PaymentBundlePdfController extends Controller
{
    public function unsigned(PaymentBundle $bundle)
    {
        abort_unless(auth()->check() && auth()->user()->can('payment_bundles.download_pdf'), 403);

        $attachment = $bundle->unsignedPdf;
        abort_unless($attachment, 404);

        return Storage::disk('private')->response(
            $attachment->file_path,
            $attachment->original_filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
