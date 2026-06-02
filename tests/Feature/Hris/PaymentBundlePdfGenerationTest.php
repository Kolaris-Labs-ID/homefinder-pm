<?php

use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundleDocumentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 5 — PaymentBundleDocumentService: unsigned PDF generation, HTML content, audit, and
 * permission-gated download route.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(\Database\Seeders\FinanceRolePermissionSeeder::class);
    $this->seed(\Database\Seeders\PaymentBundleRolePermissionSeeder::class);

    $this->doc = app(PaymentBundleDocumentService::class);
    $this->bundles = app(PaymentBundleService::class);
    $this->prs = app(PaymentRequestService::class);

    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();
    $this->actor = User::factory()->create();
});

function pdfApprovedPr(string $accountNumber = '9876543210', string $amount = '5000000'): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT PDF Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => $accountNumber,
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'PDF test payment',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

it('generateUnsignedPdf returns a FinanceAttachment stored on the private disk with PDF bytes', function () {
    $pr = pdfApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);
    $bundle->refresh();

    $attachment = $this->doc->generateUnsignedPdf($bundle, $this->actor);

    expect($attachment)->toBeInstanceOf(FinanceAttachment::class);
    expect($attachment->attachment_role)->toBe(FinanceAttachment::ROLE_PAYMENT_BUNDLE_UNSIGNED_PDF);
    expect(Storage::disk('private')->exists($attachment->file_path))->toBeTrue();

    $bytes = Storage::disk('private')->get($attachment->file_path);
    expect(substr($bytes, 0, 4))->toBe('%PDF');
});

it('sets unsigned_pdf_attachment_id, unsigned_pdf_file_hash, and canonical_payload_hash on the bundle', function () {
    $pr = pdfApprovedPr('1112223333', '7500000');
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);

    $attachment = $this->doc->generateUnsignedPdf($bundle, $this->actor);
    $bundle->refresh();

    expect($bundle->unsigned_pdf_attachment_id)->toBe($attachment->id);

    $storedBytes = Storage::disk('private')->get($attachment->file_path);
    expect($bundle->unsigned_pdf_file_hash)->toBe(hash('sha256', $storedBytes));
    expect(strlen($bundle->canonical_payload_hash))->toBe(64);
    expect(ctype_xdigit($bundle->canonical_payload_hash))->toBeTrue();
});

it('renderHtml contains required document content and masks the full bank account number', function () {
    $fullAccount = '9876543210';
    $last4 = '3210';

    $pr = pdfApprovedPr($fullAccount, '5000000');
    $bundle = $this->bundles->create($this->creator, ['title' => 'Test bundle']);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);

    // We need canonical_payload_json populated before rendering
    $this->doc->generateUnsignedPdf($bundle, $this->actor);
    $bundle->refresh();

    $html = $this->doc->renderHtml($bundle);

    expect($html)->toContain('Final Payment Authorization');
    expect($html)->toContain($bundle->bundle_number);
    expect($html)->toContain($bundle->canonical_payload_hash);
    expect($html)->toContain($last4);
    // Formatted total
    expect($html)->toContain('5,000,000');
    // Full account must NOT appear
    expect($html)->not->toContain($fullAccount);
});

it('writes a payment_bundle.unsigned_pdf_generated audit row', function () {
    $pr = pdfApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);

    $this->doc->generateUnsignedPdf($bundle, $this->actor);
    $bundle->refresh();

    expect(
        FinanceAuditLog::where('event_type', 'payment_bundle.unsigned_pdf_generated')
            ->where('auditable_id', $bundle->id)
            ->exists()
    )->toBeTrue();
});

it('returns 200 with application/pdf for a user with payment_bundles.download_pdf', function () {
    $pr = pdfApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);
    $this->doc->generateUnsignedPdf($bundle, $this->actor);
    $bundle->refresh();

    $user = User::factory()->create();
    $user->assignRole('finance_creator');

    $response = $this->actingAs($user)->get(route('finance.payment-bundles.unsigned-pdf', $bundle));

    $response->assertStatus(200);
    $response->assertHeader('content-type', 'application/pdf');
});

it('returns 403 for a user without payment_bundles.download_pdf', function () {
    $pr = pdfApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);
    $this->doc->generateUnsignedPdf($bundle, $this->actor);
    $bundle->refresh();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('finance.payment-bundles.unsigned-pdf', $bundle));

    $response->assertStatus(403);
});
