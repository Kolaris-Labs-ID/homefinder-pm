<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundleDocumentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 9 — Signed PDF generation via Blade re-render (no FPDI).
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->doc = app(PaymentBundleDocumentService::class);
    $this->prs = app(PaymentRequestService::class);

    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();

    $this->financeA = User::factory()->create();
    $this->financeA->assignRole('finance_signer');
    $this->financeB = User::factory()->create();
    $this->financeB->assignRole('finance_signer');
    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');
});

// ─── PNG / bundle helpers ──────────────────────────────────────────────────────

function signedPdfInkPng(): string
{
    $img = imagecreatetruecolor(100, 50);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    imagealphablending($img, true);
    $black = imagecolorallocate($img, 0, 0, 0);
    imageline($img, 5, 25, 95, 25, $black);
    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,'.base64_encode($bytes);
}

function signedPdfApprovedPr(int $amount = 5000000): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Signed PDF Vendor',
        'bank_name' => 'Mandiri',
        'bank_account_number' => '0012345678',
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'Signed PDF test',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function signedPdfDraftBundle(int $amount = 5000000): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, signedPdfApprovedPr($amount), test()->creator);

    return $bundle->fresh();
}

function signedPdfValidAssignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

function signedPdfSignSlot(PaymentBundle $bundle, PaymentBundleSignatureSlot $slot): PaymentBundleSignature
{
    $sig = $bundle->signatures()->where('signature_slot', $slot->value)->firstOrFail();

    return test()->signatures->submitSignature($sig, $sig->assignedUser, signedPdfInkPng());
}

// ─── 1. Generated on authorization ────────────────────────────────────────────

it('generates the signed PDF atomically when the executive signs', function () {
    $bundle = signedPdfDraftBundle();
    $bundle = $this->bundles->submitForSignature($bundle, signedPdfValidAssignment(), $this->creator);

    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    $bundle->refresh();

    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
    expect($bundle->signed_pdf_attachment_id)->not->toBeNull();

    $attachment = FinanceAttachment::find($bundle->signed_pdf_attachment_id);
    expect($attachment)->not->toBeNull();
    expect($attachment->attachment_role)->toBe(FinanceAttachment::ROLE_PAYMENT_BUNDLE_SIGNED_PDF);
    Storage::disk('private')->assertExists($attachment->file_path);

    $bytes = Storage::disk('private')->get($attachment->file_path);
    expect(substr($bytes, 0, 4))->toBe('%PDF');

    expect($bundle->signed_pdf_file_hash)->toBe(hash('sha256', $bytes));
    expect($bundle->signed_payload_hash)->toBe($bundle->canonical_payload_hash);

    expect(
        FinanceAuditLog::where('event_type', 'payment_bundle.signed_pdf_generated')
            ->where('auditable_id', $bundle->id)
            ->exists()
    )->toBeTrue();
});

// ─── 2. Unsigned PDF unchanged ─────────────────────────────────────────────────

it('does not alter the unsigned PDF when the signed PDF is generated', function () {
    $bundle = signedPdfDraftBundle();
    $bundle = $this->bundles->submitForSignature($bundle, signedPdfValidAssignment(), $this->creator);

    $unsignedId = $bundle->unsigned_pdf_attachment_id;
    $unsignedHash = $bundle->unsigned_pdf_file_hash;

    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    $bundle->refresh();

    expect($bundle->unsigned_pdf_attachment_id)->toBe($unsignedId);
    expect($bundle->unsigned_pdf_file_hash)->toBe($unsignedHash);
});

// ─── 3. Signature blocks render via Blade re-render (no FPDI) ─────────────────

it('injects signature images into the HTML via Blade re-render', function () {
    $bundle = signedPdfDraftBundle();
    $bundle = $this->bundles->submitForSignature($bundle, signedPdfValidAssignment(), $this->creator);

    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    $bundle->refresh();

    $signatures = [];
    foreach ($bundle->signatures()->where('status', PaymentBundleSignature::STATUS_SIGNED)->get() as $sig) {
        $att = $sig->signatureImage;
        $bytes = Storage::disk('private')->get($att->file_path);
        $signatures[$sig->signature_slot->value] = [
            'image_data_uri' => 'data:image/png;base64,'.base64_encode($bytes),
            'name' => $sig->signature_name,
            'role' => $sig->signature_role,
            'signed_at' => optional($sig->signed_at)->toDayDateTimeString(),
        ];
    }

    $html = $this->doc->renderHtml($bundle, $signatures);

    expect($html)->toContain($this->financeA->name);
    expect($html)->toContain($this->financeB->name);
    expect($html)->toContain($this->exec->name);
    expect($html)->toContain('data:image/png;base64,');

    // Assert no FPDI usage anywhere
    $serviceFile = file_get_contents(base_path('app/Services/Hris/PaymentBundleDocumentService.php'));
    expect($serviceFile)->not->toContain('fpdi');
    expect($serviceFile)->not->toContain('Fpdi');
    expect($serviceFile)->not->toContain('setasign');
    expect($serviceFile)->not->toContain('FPDI');
});

// ─── 4. Idempotency ────────────────────────────────────────────────────────────

it('returns the same attachment and does not duplicate on a second generateSignedPdf call', function () {
    $bundle = signedPdfDraftBundle();
    $bundle = $this->bundles->submitForSignature($bundle, signedPdfValidAssignment(), $this->creator);

    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    $bundle->refresh();
    $firstAttachmentId = $bundle->signed_pdf_attachment_id;
    $firstHash = $bundle->signed_pdf_file_hash;

    // Second call
    $second = $this->doc->generateSignedPdf($bundle, $this->exec);

    $bundle->refresh();

    expect($second->id)->toBe($firstAttachmentId);
    expect($bundle->signed_pdf_attachment_id)->toBe($firstAttachmentId);
    expect($bundle->signed_pdf_file_hash)->toBe($firstHash);

    expect(
        FinanceAttachment::where('attachment_role', FinanceAttachment::ROLE_PAYMENT_BUNDLE_SIGNED_PDF)
            ->where('attachable_id', $bundle->id)
            ->count()
    )->toBe(1);
});

// ─── 5. signed_payload_hash binds the canonical hash ──────────────────────────

it('sets signed_payload_hash equal to canonical_payload_hash', function () {
    $bundle = signedPdfDraftBundle();
    $bundle = $this->bundles->submitForSignature($bundle, signedPdfValidAssignment(), $this->creator);

    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    signedPdfSignSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    $bundle->refresh();

    expect($bundle->signed_payload_hash)->toBe($bundle->canonical_payload_hash);
});
