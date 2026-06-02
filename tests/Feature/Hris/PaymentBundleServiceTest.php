<?php

use App\Models\Hris\FinanceAuditLog;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3 — PaymentBundle assembly: create, add PR / weekly-allowance batch, frozen+encrypted
 * snapshot, single-active-bundle uniqueness, total via bcmath, remove/release.
 */
beforeEach(function () {
    $this->bundles = app(PaymentBundleService::class);
    $this->prs = app(PaymentRequestService::class);
    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();
    $this->actor = User::factory()->create();
});

function approvedPr(array $overrides = []): PaymentRequest
{
    $svc = test()->prs;
    $pr = $svc->createManual(test()->creator, array_merge([
        'payee_type' => 'vendor',
        'payee_name' => 'PT Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => 5000000,
        'currency' => 'IDR',
        'description' => 'Office supplies',
    ], $overrides));
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, test()->coo);

    return $pr->fresh();
}

function eligibleWab(string $amount = '3000000'): WeeklyAllowanceBatch
{
    return WeeklyAllowanceBatch::create([
        'week_start_date' => '2026-05-25',
        'week_end_date' => '2026-05-31',
        'payment_date' => '2026-06-02',
        'status' => WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE,
        'total_employees' => 4,
        'total_amount' => $amount,
    ]);
}

it('creates a draft bundle with PB number, verification code, zero total, and an audit row', function () {
    $bundle = $this->bundles->create($this->creator, ['title' => 'June payout']);

    expect($bundle->status)->toBe(PaymentBundle::STATUS_DRAFT);
    expect($bundle->bundle_number)->toStartWith('PB-'.now()->format('Ym').'-');
    expect($bundle->public_verification_code)->not->toBeEmpty();
    expect($bundle->total_amount)->toBe('0.00');
    expect($bundle->bundle_version)->toBe(1);
    expect($bundle->created_by)->toBe($this->creator->id);

    expect(FinanceAuditLog::where('event_type', 'payment_bundle.created')
        ->where('auditable_id', $bundle->id)->exists())->toBeTrue();
});

it('adds an approved PR as a frozen, full-account snapshot item and flips the PR status', function () {
    $pr = approvedPr(['bank_account_number' => '9876543210', 'amount' => 5000000]);
    $bundle = $this->bundles->create($this->creator);

    $item = $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);

    expect($item->status)->toBe(PaymentBundleItem::STATUS_ACTIVE);

    $snap = $item->source_snapshot_json; // decrypted by cast
    expect($snap['payee_name'])->toBe('PT Vendor');
    expect($snap['bank_account_last4'])->toBe('3210');
    expect($snap['bank_account_number'])->toBe('9876543210'); // full account frozen
    expect($snap['amount'])->toBe('5000000.00');

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_IN_PAYMENT_BUNDLE);
    expect(bccomp((string) $bundle->fresh()->total_amount, '5000000', 2))->toBe(0);
});

it('refuses to add the same approved PR to a second active bundle', function () {
    $pr = approvedPr();
    $bundleA = $this->bundles->create($this->creator);
    $bundleB = $this->bundles->create($this->creator);

    $this->bundles->addPaymentRequest($bundleA, $pr, $this->actor);

    expect(fn () => $this->bundles->addPaymentRequest($bundleB, $pr->fresh(), $this->actor))
        ->toThrow(RuntimeException::class);
});

it('adds an eligible weekly allowance batch and flips its status', function () {
    $wab = eligibleWab('3000000');
    $bundle = $this->bundles->create($this->creator);

    $item = $this->bundles->addWeeklyAllowanceBatch($bundle, $wab, $this->actor);

    expect($item->status)->toBe(PaymentBundleItem::STATUS_ACTIVE);
    expect($item->payee_bank_name)->toBeNull();
    expect($item->source_snapshot_json['total_employees'])->toBe(4);
    expect($wab->fresh()->status)->toBe(WeeklyAllowanceBatch::STATUS_IN_PAYMENT_BUNDLE);
    expect(bccomp((string) $bundle->fresh()->total_amount, '3000000', 2))->toBe(0);
});

it('totals the sum of multiple active items', function () {
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, approvedPr(['amount' => 5000000]), $this->actor);
    $this->bundles->addWeeklyAllowanceBatch($bundle, eligibleWab('3000000'), $this->actor);

    expect(bccomp((string) $bundle->fresh()->total_amount, '8000000', 2))->toBe(0);
});

it('releases the source and excludes released items from the total on removeItem', function () {
    $pr = approvedPr(['amount' => 5000000]);
    $wab = eligibleWab('3000000');
    $bundle = $this->bundles->create($this->creator);

    $prItem = $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);
    $wabItem = $this->bundles->addWeeklyAllowanceBatch($bundle, $wab, $this->actor);

    $this->bundles->removeItem($prItem, $this->actor);

    expect($prItem->fresh()->status)->toBe(PaymentBundleItem::STATUS_RELEASED);
    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_APPROVED_FOR_BUNDLE);
    expect(bccomp((string) $bundle->fresh()->total_amount, '3000000', 2))->toBe(0); // only WAB remains

    $this->bundles->removeItem($wabItem, $this->actor);
    expect($wab->fresh()->status)->toBe(WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE);
    expect(bccomp((string) $bundle->fresh()->total_amount, '0', 2))->toBe(0);
});

it('rejects adding to a non-draft bundle and adding a non-approved PR', function () {
    $bundle = $this->bundles->create($this->creator);
    $bundle->update(['status' => PaymentBundle::STATUS_SUBMITTED_FOR_SIGNATURE]);

    expect(fn () => $this->bundles->addPaymentRequest($bundle, approvedPr(), $this->actor))
        ->toThrow(RuntimeException::class);

    $draftBundle = $this->bundles->create($this->creator);
    $draftPr = $this->prs->createManual($this->creator, [
        'payee_type' => 'vendor', 'payee_name' => 'PT X', 'bank_name' => 'BCA',
        'bank_account_number' => '111', 'amount' => 1000, 'currency' => 'IDR', 'description' => 'x',
    ]); // still draft, never approved

    expect(fn () => $this->bundles->addPaymentRequest($draftBundle, $draftPr, $this->actor))
        ->toThrow(RuntimeException::class);
});

it('stores the source snapshot encrypted at rest (no plaintext account on disk)', function () {
    $pr = approvedPr(['bank_account_number' => '9876543210']);
    $bundle = $this->bundles->create($this->creator);
    $this->bundles->addPaymentRequest($bundle, $pr, $this->actor);

    $raw = DB::table('payment_bundle_items')->value('source_snapshot_json');
    expect($raw)->not->toContain('9876543210');
    expect($raw)->not->toContain('PT Vendor');
});
