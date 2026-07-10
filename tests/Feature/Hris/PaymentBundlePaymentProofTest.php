<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundlePaymentService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 11 — Payment proof upload, markPaid, rejectPaymentProof, SoD enforcement.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->payments = app(PaymentBundlePaymentService::class);
    $this->prs = app(PaymentRequestService::class);

    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();

    $this->financeA = User::factory()->create();
    $this->financeA->assignRole('finance_signer');
    $this->financeB = User::factory()->create();
    $this->financeB->assignRole('finance_signer');
    $this->exec = User::factory()->create();
    $this->exec->assignRole('executive_signer');

    $this->payer = User::factory()->create();
});

// ─── helpers ───────────────────────────────────────────────────────────────────

function proofInkPng(): string
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

function proofApprovedPr(int $amount = 5000000, string $account = '1234567890'): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Test Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => $account,
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'Test payment',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function proofValidAssignment(): array
{
    return [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
}

function proofSignSlot(PaymentBundle $bundle, PaymentBundleSignatureSlot $slot): void
{
    $sig = $bundle->signatures()->where('signature_slot', $slot->value)->firstOrFail();
    test()->signatures->submitSignature($sig, $sig->assignedUser, proofInkPng());
}

function proofAuthorizedBundle(PaymentRequest $pr): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, $pr, test()->creator);
    $bundle = test()->bundles->submitForSignature($bundle->fresh(), proofValidAssignment(), test()->creator);

    proofSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_1);
    proofSignSlot($bundle, PaymentBundleSignatureSlot::FINANCE_SIGNER_2);
    proofSignSlot($bundle, PaymentBundleSignatureSlot::EXECUTIVE_SIGNER);

    return $bundle->fresh();
}

function fakePdf(string $name = 'proof.pdf'): UploadedFile
{
    return UploadedFile::fake()->create($name, 50, 'application/pdf');
}

// ─── 1. markPaid happy path ─────────────────────────────────────────────────────

it('marks an authorized bundle paid with a proof file', function () {
    $pr = proofApprovedPr(5000000);
    $bundle = proofAuthorizedBundle($pr);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);

    $result = $this->payments->markPaid($bundle, $this->payer, fakePdf(), 'REF-001', 'Paid via BCA transfer');

    expect($result->status)->toBe(PaymentBundle::STATUS_PAID);
    expect($result->paid_by)->toBe($this->payer->id);
    expect($result->paid_at)->not->toBeNull();
    expect($result->payment_reference)->toBe('REF-001');
    expect($result->payment_proof_attachment_id)->not->toBeNull();

    // Attachment stored on private disk with correct role.
    $attachment = FinanceAttachment::find($result->payment_proof_attachment_id);
    expect($attachment)->not->toBeNull();
    expect($attachment->attachment_role)->toBe(FinanceAttachment::ROLE_PAYMENT_PROOF);
    Storage::disk('private')->assertExists($attachment->file_path);

    // Items cascaded to paid.
    expect($bundle->fresh()->items()->where('status', PaymentBundleItem::STATUS_PAID)->count())->toBe(1);
    expect($bundle->fresh()->items()->whereNotIn('status', [PaymentBundleItem::STATUS_PAID])->count())->toBe(0);

    // Linked PaymentRequest marked paid.
    expect(PaymentRequest::find($pr->id)->status)->toBe(PaymentRequest::STATUS_PAID);

    // Both audit events written.
    expect(FinanceAuditLog::where('event_type', 'payment_bundle.payment_proof_uploaded')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
    expect(FinanceAuditLog::where('event_type', 'payment_bundle.marked_paid')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

it('marks a payment_recording bundle paid', function () {
    $pr = proofApprovedPr(5000000);
    $bundle = proofAuthorizedBundle($pr);
    $this->payments->beginPaymentRecording($bundle, $this->creator);

    $result = $this->payments->markPaid($bundle->fresh(), $this->payer, fakePdf());

    expect($result->status)->toBe(PaymentBundle::STATUS_PAID);
    expect($result->payment_proof_attachment_id)->not->toBeNull();
});

// ─── 2. Proof is required (typed param enforces it; happy paths cover storage) ──

it('stores the proof attachment on every markPaid call', function () {
    $pr = proofApprovedPr(5000000);
    $bundle = proofAuthorizedBundle($pr);

    $countBefore = FinanceAttachment::where('attachment_role', FinanceAttachment::ROLE_PAYMENT_PROOF)->count();

    $this->payments->markPaid($bundle, $this->payer, fakePdf());

    expect(FinanceAttachment::where('attachment_role', FinanceAttachment::ROLE_PAYMENT_PROOF)->count())
        ->toBe($countBefore + 1);
});

// ─── 3. Partially-signed bundle cannot be paid ─────────────────────────────────

it('throws when marking a partially-signed bundle paid', function () {
    $pr = proofApprovedPr();
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);
    $bundle = $this->bundles->submitForSignature($bundle->fresh(), proofValidAssignment(), $this->creator);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);

    expect(fn () => $this->payments->markPaid($bundle, $this->payer, fakePdf()))
        ->toThrow(RuntimeException::class, 'Only an authorized/recording/proof-rejected bundle can be marked paid.');

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);
});

it('throws when marking a draft bundle paid', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, proofApprovedPr(), $this->creator);

    expect(fn () => $this->payments->markPaid($bundle->fresh(), $this->payer, fakePdf()))
        ->toThrow(RuntimeException::class);
});

// ─── 4. Signed PDF required ─────────────────────────────────────────────────────

it('throws when the signed PDF is missing', function () {
    $pr = proofApprovedPr();
    $bundle = proofAuthorizedBundle($pr);
    $bundle->forceFill(['signed_pdf_attachment_id' => null])->save();

    expect(fn () => $this->payments->markPaid($bundle->fresh(), $this->payer, fakePdf()))
        ->toThrow(RuntimeException::class, 'A signed authorization document is required.');

    expect($bundle->fresh()->status)->toBe(PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT);
});

// ─── 5. Reject proof ────────────────────────────────────────────────────────────

it('rejects the payment proof of a paid bundle', function () {
    $pr = proofApprovedPr(5000000);
    $bundle = proofAuthorizedBundle($pr);
    $paid = $this->payments->markPaid($bundle, $this->payer, fakePdf());
    $proofAttachmentId = $paid->payment_proof_attachment_id;

    $rejected = $this->payments->rejectPaymentProof($paid, $this->creator, 'Blurry scan, amount unreadable');

    expect($rejected->status)->toBe(PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED);

    // Proof attachment is STILL present — not deleted.
    expect($rejected->payment_proof_attachment_id)->toBe($proofAttachmentId);
    expect(FinanceAttachment::find($proofAttachmentId))->not->toBeNull();

    // Rejection reason recorded in metadata.
    expect($rejected->metadata['payment_proof_rejection_reason'])->toBe('Blurry scan, amount unreadable');
    expect($rejected->metadata['payment_proof_rejected_by'])->toBe($this->creator->id);

    // Audit event written.
    expect(FinanceAuditLog::where('event_type', 'payment_bundle.payment_proof_rejected')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

it('throws when rejecting proof on a non-paid bundle', function () {
    $pr = proofApprovedPr();
    $bundle = proofAuthorizedBundle($pr);

    expect(fn () => $this->payments->rejectPaymentProof($bundle, $this->creator, 'Bad proof'))
        ->toThrow(RuntimeException::class, 'Only a paid bundle can have its payment proof rejected.');
});

// ─── 6. Re-mark paid after rejection ────────────────────────────────────────────

it('accepts a new proof after rejection and returns to paid', function () {
    $pr = proofApprovedPr(5000000);
    $bundle = proofAuthorizedBundle($pr);

    $paid = $this->payments->markPaid($bundle, $this->payer, fakePdf('proof_v1.pdf'), 'REF-ORIG');
    $firstProofId = $paid->payment_proof_attachment_id;

    $rejected = $this->payments->rejectPaymentProof($paid, $this->creator, 'Wrong document');
    expect($rejected->status)->toBe(PaymentBundle::STATUS_PAYMENT_PROOF_REJECTED);

    $rePaid = $this->payments->markPaid($rejected, $this->payer, fakePdf('proof_v2.pdf'), 'REF-CORRECTED');

    expect($rePaid->status)->toBe(PaymentBundle::STATUS_PAID);
    expect($rePaid->payment_reference)->toBe('REF-CORRECTED');

    // A genuinely NEW proof replaced the rejected one.
    expect($rePaid->payment_proof_attachment_id)->not->toBeNull();
    expect($rePaid->payment_proof_attachment_id)->not->toBe($firstProofId);
    $newAttachment = FinanceAttachment::find($rePaid->payment_proof_attachment_id);
    expect($newAttachment)->not->toBeNull();
    Storage::disk('private')->assertExists($newAttachment->file_path);
});

it('throws when marking an already-paid bundle paid again', function () {
    $bundle = proofAuthorizedBundle(proofApprovedPr(5000000));
    $this->payments->markPaid($bundle, $this->payer, fakePdf());

    expect(fn () => $this->payments->markPaid($bundle->fresh(), $this->payer, fakePdf()))
        ->toThrow(RuntimeException::class);
});

// ─── 7. Payer identity no longer restricted (SoD removed) ────────────────────────

it('allows the executive signer to pay a bundle', function () {
    $pr = proofApprovedPr(10_000_000);
    $bundle = proofAuthorizedBundle($pr);

    $result = $this->payments->markPaid($bundle, $this->exec, fakePdf());
    expect($result->status)->toBe(PaymentBundle::STATUS_PAID);
});

it('allows a finance signer to pay a bundle they signed (SoD removed)', function () {
    // financeA signed finance_signer_1 — previously blocked as payer, now permitted.
    $pr = proofApprovedPr(10_000_000);
    $bundle = proofAuthorizedBundle($pr);

    $result = $this->payments->markPaid($bundle, $this->financeA, fakePdf());
    expect($result->status)->toBe(PaymentBundle::STATUS_PAID);
});
