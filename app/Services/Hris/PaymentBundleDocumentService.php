<?php

namespace App\Services\Hris;

use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\PaymentBundle;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Generates the canonical-payload-bound PDF for a payment bundle (Phase 5).
 * A single Blade template serves both unsigned (empty $signatures) and signed (Phase 9).
 */
class PaymentBundleDocumentService
{
    public function __construct(
        private CanonicalPayloadHashService $canonical,
        private FinanceAttachmentService $attachments,
        private FinanceAuditLogger $audit,
    ) {}

    /**
     * Render the authorization Blade to HTML. $signatures is slot => array with keys:
     * image_data_uri, name, role, signed_at — empty for unsigned.
     */
    public function renderHtml(PaymentBundle $bundle, array $signatures = []): string
    {
        $verificationUrl = rtrim(config('app.url'), '/').'/verify/payment-document/'.$bundle->public_verification_code;

        $qrDataUri = (new \Endroid\QrCode\Builder\Builder)->build(
            writer: new \Endroid\QrCode\Writer\PngWriter,
            data: $verificationUrl,
            size: 140,
            margin: 8,
        )->getDataUri();

        return view('finance.payment-bundles.authorization', [
            'bundle' => $bundle,
            'payload' => $bundle->canonical_payload_json,
            'qrDataUri' => $qrDataUri,
            'verificationUrl' => $verificationUrl,
            'signatures' => $signatures,
        ])->render();
    }

    public function generateUnsignedPdf(PaymentBundle $bundle, User $actor): FinanceAttachment
    {
        return DB::transaction(function () use ($bundle, $actor) {
            $this->canonical->store($bundle);
            $bundle->refresh();

            $html = $this->renderHtml($bundle);
            $bytes = Pdf::loadHtml($html)->output();

            $attachment = $this->attachments->storeGeneratedFile(
                $bundle,
                $bytes,
                FinanceAttachment::ROLE_PAYMENT_BUNDLE_UNSIGNED_PDF,
                "bundle-{$bundle->bundle_number}-unsigned.pdf",
                'application/pdf',
                $actor,
            );

            $pdfHash = hash('sha256', $bytes);

            $bundle->update([
                'unsigned_pdf_attachment_id' => $attachment->id,
                'unsigned_pdf_file_hash' => $pdfHash,
            ]);

            $this->audit->log($bundle, 'payment_bundle.unsigned_pdf_generated', $actor, [
                'after' => [
                    'attachment_id' => $attachment->id,
                    'unsigned_pdf_file_hash' => $pdfHash,
                    'canonical_payload_hash' => $bundle->canonical_payload_hash,
                ],
            ]);

            return $attachment;
        });
    }
}
