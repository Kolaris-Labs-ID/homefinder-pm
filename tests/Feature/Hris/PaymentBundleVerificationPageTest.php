<?php

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundle;
use App\Models\User;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

/**
 * Phase 12 — Verification page: public, rate-limited, privacy-preserving.
 */
beforeEach(function () {
    Storage::fake('private');

    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);
    $this->prs = app(PaymentRequestService::class);

    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();

    $this->financeA = User::factory()->create(['name' => 'Alice Finance']);
    $this->financeA->assignRole('finance_signer');
    $this->financeB = User::factory()->create(['name' => 'Bob Finance']);
    $this->financeB->assignRole('finance_signer');
    $this->exec = User::factory()->create(['name' => 'Carol Executive']);
    $this->exec->assignRole('executive_signer');
});

// ─── helpers ──────────────────────────────────────────────────────────────────

function verifyInkPng(): string
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

function verifyApprovedPr(): \App\Models\Hris\PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT Secret Payee',
        'bank_name' => 'BNI',
        'bank_account_number' => '9988776655',
        'amount' => 7500000,
        'currency' => 'IDR',
        'description' => 'Verification test item',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function verifyAuthorizedBundle(): PaymentBundle
{
    $bundle = test()->bundles->create(test()->creator);
    test()->bundles->addPaymentRequest($bundle, verifyApprovedPr(), test()->creator);

    $assignment = [
        PaymentBundleSignatureSlot::FINANCE_SIGNER_1->value => test()->financeA->id,
        PaymentBundleSignatureSlot::FINANCE_SIGNER_2->value => test()->financeB->id,
        PaymentBundleSignatureSlot::EXECUTIVE_SIGNER->value => test()->exec->id,
    ];
    $bundle = test()->bundles->submitForSignature($bundle, $assignment, test()->creator);

    foreach (PaymentBundleSignatureSlot::ordered() as $slot) {
        $sig = $bundle->signatures()->where('signature_slot', $slot->value)->firstOrFail();
        test()->signatures->submitSignature($sig, $sig->assignedUser, verifyInkPng());
    }

    return $bundle->fresh();
}

// ─── 1. Valid code → 200 with bundle_number + hash + status ──────────────────

it('returns 200 with bundle_number, canonical_hash, and status label for a valid code', function () {
    $bundle = verifyAuthorizedBundle();

    $response = $this->get('/verify/payment-document/'.$bundle->public_verification_code);

    $response->assertOk();
    $response->assertSee($bundle->bundle_number);
    $response->assertSee($bundle->canonical_payload_hash);
    $response->assertSee('Authorized For Payment', false);
});

// ─── 2. No PII in response ─────────────────────────────────────────────────

it('does not expose payee name, signer names, bank account, or amount', function () {
    $bundle = verifyAuthorizedBundle();

    $response = $this->get('/verify/payment-document/'.$bundle->public_verification_code);

    $response->assertOk();
    $response->assertDontSee('PT Secret Payee');
    $response->assertDontSee('Alice Finance');
    $response->assertDontSee('Bob Finance');
    $response->assertDontSee('Carol Executive');
    $response->assertDontSee('9988776655');
    $response->assertDontSee('7500000');
    $response->assertDontSee('7,500,000');
});

// ─── 3. Hash match → positive indicator ───────────────────────────────────

it('shows a positive match indicator when the correct hash is passed', function () {
    $bundle = verifyAuthorizedBundle();

    $response = $this->get('/verify/payment-document/'.$bundle->public_verification_code.'?hash='.$bundle->canonical_payload_hash);

    $response->assertOk();
    $response->assertSee('matches', false);
});

// ─── 4. Hash mismatch (forged document) → negative indicator ──────────────

it('shows a mismatch indicator when a wrong hash is passed', function () {
    $bundle = verifyAuthorizedBundle();
    $wrongHash = str_repeat('f', 64);

    $response = $this->get('/verify/payment-document/'.$bundle->public_verification_code.'?hash='.$wrongHash);

    $response->assertOk();
    $response->assertSee('NOT match', false);
    $response->assertDontSee('matches', false);
});

// ─── 5. Unknown code → 200 generic, no PII, no real hash ─────────────────

it('returns 200 with a generic not-recognized message for an unknown code', function () {
    $bundle = verifyAuthorizedBundle();

    $response = $this->get('/verify/payment-document/totally-unknown-code-xyz');

    $response->assertOk();
    $response->assertSee('not recognized', false);
    $response->assertDontSee($bundle->canonical_payload_hash);
    $response->assertDontSee('PT Secret Payee');
    $response->assertDontSee($bundle->bundle_number);
});

// ─── 6. Rate limit on the route ───────────────────────────────────────────

it('has throttle middleware on the verify route', function () {
    $route = app('router')->getRoutes()->getByName('verify.payment-document');
    expect($route)->not->toBeNull();

    $middleware = collect($route->gatherMiddleware())->implode(',');
    expect($middleware)->toContain('throttle:30,1');
});
