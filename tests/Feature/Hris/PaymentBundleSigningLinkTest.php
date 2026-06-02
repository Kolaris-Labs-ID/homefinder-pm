<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\User;
use App\Services\Hris\PaymentBundleSignatureService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 6 — Signing link & auth redirect. Two-layer gate: signed-URL HMAC (tamper-proof, non-expiring)
 * + DB token state (expiry / one-time-use / revocation, per-signature). Preview must not burn the token.
 */
beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundle = PaymentBundle::factory()->create([
        'canonical_payload_hash' => str_repeat('a', 64),
    ]);
    $this->sig = PaymentBundleSignature::factory()->create([
        'payment_bundle_id' => $this->bundle->id,
        'signature_slot' => PaymentBundleSignatureSlot::FINANCE_SIGNER_1,
    ]);

    $this->signer = User::factory()->create();
    $this->signer->assignRole('finance_signer');

    $this->svc = app(PaymentBundleSignatureService::class);
});

it('createSigningLink stores a hashed token, future expiry, assignment, and writes an audit row', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);
    $this->sig->refresh();

    expect($this->sig->signature_token_hash)->not->toBeNull();
    expect(strlen($this->sig->signature_token_hash))->toBe(64);
    // The raw token (64 random chars) must never be persisted verbatim.
    expect($url)->toContain('token=');
    $raw = (function () use ($url) {
        parse_str(parse_url($url, PHP_URL_QUERY), $q);

        return $q['token'];
    })();
    expect($this->sig->signature_token_hash)->not->toBe($raw);
    expect($this->sig->signature_token_hash)->toBe(hash('sha256', $raw));

    expect($this->sig->token_expires_at->isFuture())->toBeTrue();
    expect($this->sig->assigned_user_id)->toBe($this->signer->id);
    expect($this->sig->required_permission)->toBe('payment_bundles.sign.finance');
    expect($this->sig->status)->toBe(PaymentBundleSignature::STATUS_PENDING);
    expect($this->sig->token_used_at)->toBeNull();
    expect($this->sig->token_revoked_at)->toBeNull();

    // URL carries both the token AND the signature HMAC query param.
    expect($url)->toContain('signature=');

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.signature_link_created')
        ->where('auditable_type', PaymentBundleSignature::class)
        ->where('auditable_id', $this->sig->id)
        ->exists())->toBeTrue();
});

it('previews for the assigned signer (200) without burning the token, and audits the view', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);

    $this->actingAs($this->signer)->get($url)->assertOk();

    $this->sig->refresh();
    expect($this->sig->token_used_at)->toBeNull();

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.signature_preview_viewed')
        ->where('auditable_id', $this->sig->id)
        ->exists())->toBeTrue();
});

it('rejects an expired token with 403', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);
    $this->sig->forceFill(['token_expires_at' => now()->subMinute()])->save();

    $this->actingAs($this->signer)->get($url)->assertForbidden();
});

it('rejects a revoked token with 403', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);
    $this->svc->revokeToken($this->sig);

    $this->actingAs($this->signer)->get($url)->assertForbidden();
});

it('rejects a used token with 403', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);
    $this->svc->consumeToken($this->sig);

    $this->actingAs($this->signer)->get($url)->assertForbidden();
});

it('rejects a different authenticated user with 403 (wrong-user gate)', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);

    $other = User::factory()->create();
    $other->assignRole('finance_signer');

    $this->actingAs($other)->get($url)->assertForbidden();
});

it('rejects the assigned signer who lacks the required permission with 403 (permission gate)', function () {
    // Assigned to the link but holds no role/permission — identity gate passes, permission gate must block.
    $noPerm = User::factory()->create();
    $url = $this->svc->createSigningLink($this->sig, $noPerm);

    $this->actingAs($noPerm)->get($url)->assertForbidden();
});

it('rejects a tampered URL with 403 (broken HMAC)', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $tampered = str_replace('token='.$q['token'], 'token='.str_repeat('z', 64), $url);

    $this->actingAs($this->signer)->get($tampered)->assertForbidden();
});

it('redirects a guest to login (302), preserving the intended signed URL', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);

    $response = $this->get($url);

    $response->assertStatus(302);
    $response->assertRedirect(route('login'));

    // The full signed URL (token + HMAC) is stashed so login returns the guest to the exact signing link.
    // Compare path + params rather than the raw string (Laravel may reorder the query on re-serialization).
    $intended = session()->get('url.intended');
    expect($intended)->not->toBeNull();
    expect(parse_url($intended, PHP_URL_PATH))->toBe(parse_url($url, PHP_URL_PATH));
    parse_str(parse_url($intended, PHP_URL_QUERY) ?? '', $intendedQ);
    parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $urlQ);
    expect($intendedQ)->toEqual($urlQ);
});

it('bounces a guest on a tampered URL to login first, then 403 once authenticated (no page access)', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $tampered = str_replace('token='.$q['token'], 'token='.str_repeat('z', 64), $url);

    // Laravel's middleware PRIORITY pins `auth` ahead of `signed`, so a guest is redirected to login
    // before the HMAC is checked. No access is granted: once authenticated, `signed` rejects → 403.
    $this->get($tampered)->assertStatus(302);
    $this->actingAs($this->signer)->get($tampered)->assertForbidden();
});

it('consumeToken stamps token_used_at and flips tokenStatus/assertTokenValid', function () {
    $url = $this->svc->createSigningLink($this->sig, $this->signer);
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $raw = $q['token'];

    expect($this->svc->tokenStatus($this->sig->fresh(), $raw))->toBe('valid');

    $this->svc->consumeToken($this->sig);
    $this->sig->refresh();

    expect($this->sig->token_used_at)->not->toBeNull();
    expect($this->svc->tokenStatus($this->sig, $raw))->toBe('used');
    $this->svc->assertTokenValid($this->sig, $raw);
})->throws(RuntimeException::class);

it('returns 404 when the signature does not belong to the bundle', function () {
    $this->svc->createSigningLink($this->sig, $this->signer);

    $otherBundle = PaymentBundle::factory()->create();
    parse_str(parse_url($this->svc->createSigningLink($this->sig, $this->signer), PHP_URL_QUERY), $q);

    // Build a valid signed URL but point {bundle} at a different bundle id → mismatch → 404.
    $mismatched = \Illuminate\Support\Facades\URL::signedRoute('sign.payment-bundle', [
        'bundle' => $otherBundle->id,
        'paymentSignature' => $this->sig->id,
        'token' => $q['token'],
    ]);

    $this->actingAs($this->signer)->get($mismatched)->assertNotFound();
});
