<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAttachment;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\User;
use App\Services\Hris\PaymentBundleSignatureService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // submitSignature is now workflow-aware: finance_signer_1 ⇔ bundle status pending_finance_signature_1.
    $this->bundle = PaymentBundle::factory()->create([
        'status' => PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1,
        'canonical_payload_hash' => str_repeat('a', 64),
    ]);
    $this->sig = PaymentBundleSignature::factory()->create([
        'payment_bundle_id' => $this->bundle->id,
        'signature_slot' => PaymentBundleSignatureSlot::FINANCE_SIGNER_1,
    ]);

    $this->signer = User::factory()->create();
    $this->signer->assignRole('finance_signer');

    $this->svc = app(PaymentBundleSignatureService::class);
    $this->signedUrl = $this->svc->createSigningLink($this->sig, $this->signer);
});

// ─── PNG helpers ──────────────────────────────────────────────────────────────

function inkPng(): string
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

function blankPng(): string
{
    $img = imagecreatetruecolor(100, 50);
    imagesavealpha($img, true);
    imagealphablending($img, false);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);
    ob_start();
    imagepng($img);
    $bytes = ob_get_clean();
    imagedestroy($img);

    return 'data:image/png;base64,'.base64_encode($bytes);
}

// ─── 1. Valid signature POST ───────────────────────────────────────────────────

it('accepts a valid ink signature and marks the slot signed', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
            'confirmed' => '1',
        ])
        ->assertRedirect(route('sign.payment-bundle.thanks'));

    $this->sig->refresh();
    expect($this->sig->status)->toBe(PaymentBundleSignature::STATUS_SIGNED);
    expect($this->sig->signed_by)->toBe($this->signer->id);
    expect($this->sig->signed_at)->not->toBeNull();
    expect($this->sig->signature_image_attachment_id)->not->toBeNull();
    expect(strlen($this->sig->signature_hash))->toBe(64);
    expect($this->sig->token_used_at)->not->toBeNull();

    // Audit/identity metadata captured on the row.
    expect($this->sig->signature_name)->toBe($this->signer->name);
    expect($this->sig->signature_role)->toBe(PaymentBundleSignatureSlot::FINANCE_SIGNER_1->label());
    expect($this->sig->signature_ip)->not->toBeNull();
});

it('lands the signer on the token-free thank-you page after signing', function () {
    $this->actingAs($this->signer)->post($this->signedUrl, [
        'signature_image' => inkPng(),
        'confirmed' => '1',
    ]);

    // The signing URL is now dead (token consumed); the thanks page is reachable token-free.
    $this->actingAs($this->signer)->get(route('sign.payment-bundle.thanks'))->assertOk();
});

it('stores the signature image on the private disk with the correct role', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
            'confirmed' => '1',
        ]);

    $this->sig->refresh();
    $attachment = FinanceAttachment::find($this->sig->signature_image_attachment_id);

    expect($attachment)->not->toBeNull();
    expect($attachment->attachment_role)->toBe(FinanceAttachment::ROLE_PAYMENT_BUNDLE_SIGNATURE_IMAGE);
    Storage::disk('private')->assertExists($attachment->file_path);
});

it('writes a payment_bundle.signature_submitted audit row', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
            'confirmed' => '1',
        ]);

    expect(
        FinanceAuditLog::where('event_type', 'payment_bundle.signature_submitted')
            ->where('auditable_type', PaymentBundleSignature::class)
            ->where('auditable_id', $this->sig->id)
            ->exists()
    )->toBeTrue();
});

// ─── 2. signature_hash formula ────────────────────────────────────────────────

it('computes signature_hash with the exact formula', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
            'confirmed' => '1',
        ]);

    $this->sig->refresh();
    $attachment = FinanceAttachment::find($this->sig->signature_image_attachment_id);

    $expected = hash('sha256',
        $this->signer->id
        .'finance_signer_1'
        .$this->bundle->canonical_payload_hash
        .$this->sig->signed_at->toIso8601String()
        .$attachment->file_hash
    );

    expect($this->sig->signature_hash)->toBe($expected);
});

// ─── 3. Blank / missing image blocked ─────────────────────────────────────────

it('rejects a blank (fully-transparent) PNG and keeps the slot pending', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => blankPng(),
            'confirmed' => '1',
        ])
        ->assertRedirect();

    $this->sig->refresh();
    expect($this->sig->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
    expect($this->sig->token_used_at)->toBeNull();
});

it('rejects a blank PNG with a session error', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => blankPng(),
            'confirmed' => '1',
        ])
        ->assertSessionHasErrors('signature_image');
});

it('rejects a missing signature_image with a validation error', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'confirmed' => '1',
        ])
        ->assertSessionHasErrors('signature_image');

    $this->sig->refresh();
    expect($this->sig->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
});

// ─── 4. Confirmation checkbox required ────────────────────────────────────────

it('rejects a valid image when the confirmation checkbox is absent', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
        ])
        ->assertSessionHasErrors('confirmed');

    $this->sig->refresh();
    expect($this->sig->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
});

// ─── 5. Double-submit blocked ─────────────────────────────────────────────────

it('blocks a second submit after the token is consumed', function () {
    // First POST — should succeed
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
            'confirmed' => '1',
        ]);

    $attachmentCountBefore = FinanceAttachment::where('attachment_role', FinanceAttachment::ROLE_PAYMENT_BUNDLE_SIGNATURE_IMAGE)->count();

    // Second POST to the same signed URL — token is now used, assertTokenValid will throw → 403
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
            'confirmed' => '1',
        ])
        ->assertForbidden();

    expect(
        FinanceAttachment::where('attachment_role', FinanceAttachment::ROLE_PAYMENT_BUNDLE_SIGNATURE_IMAGE)->count()
    )->toBe($attachmentCountBefore);
});

// ─── 6. Wrong user → 403 ──────────────────────────────────────────────────────

it('rejects a different authenticated user with 403 and stores nothing', function () {
    $other = User::factory()->create();
    $other->assignRole('finance_signer');

    $this->actingAs($other)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
            'confirmed' => '1',
        ])
        ->assertForbidden();

    $this->sig->refresh();
    expect($this->sig->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
    expect($this->sig->signature_image_attachment_id)->toBeNull();
});

// ─── 7. Private disk confirmed in valid-signature test (duplicate check via storage) ──

it('confirms the attachment file_path is on the private disk', function () {
    $this->actingAs($this->signer)
        ->post($this->signedUrl, [
            'signature_image' => inkPng(),
            'confirmed' => '1',
        ]);

    $this->sig->refresh();
    $attachment = FinanceAttachment::find($this->sig->signature_image_attachment_id);

    Storage::disk('private')->assertExists($attachment->file_path);
    expect(str_starts_with($attachment->file_path, 'finance-attachments/payment_bundle_signature_image/'))->toBeTrue();
});
