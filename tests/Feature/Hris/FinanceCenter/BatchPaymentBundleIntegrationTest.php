<?php

/**
 * Phase 10 — Batch payment bundle integration (gap-fill + frozen-clock).
 *
 * Coverage classification (pre-existing, NOT duplicated here):
 *  WeeklyAllowanceBatch bundling (addSource, total match, ineligible reject, cascade paid/release):
 *      WeeklyAllowancePaymentBundleTest ✓   BatchPaymentBundleServiceTest ✓
 *  OvertimePayBatch bundling (bundled, ineligible, drift, paid cascade, release):
 *      OvertimePayPaymentBundleTest ✓       BatchPaymentBundleServiceTest ✓
 *  LatePayCompensationBatch bundling (bundled, duplicate guard, drift, paid cascade, release):
 *      LatePayCompensationPaymentBundleTest ✓   BatchPaymentBundleServiceTest ✓
 *  Payroll gate (PayrunBatch blocked → BLOCKED status):
 *      PayrunBatchPaymentBundleTest ✓
 *  Duplicate active bundle (DB uniqueness constraint):
 *      BatchPaymentActiveUniquenessTest ✓   BatchPaymentBundleServiceTest ✓
 *  Source drift blocks submit/sign/pay:
 *      BatchPaymentBundleAuthorizationTest ✓   OvertimePayPaymentBundleTest ✓
 *  Void/cancel releases source; paid bundle never releases:
 *      BatchPaymentBundleReverseTransitionTest ✓   BatchPaymentSourceStateTest ✓
 *
 * Net-new in this file:
 *  1. Ineligible-source rejection leaves a valid co-source in the same bundle untouched.
 *  2. Frozen-clock: createSigningLink token still valid just before 72 h TTL expiry; expired
 *     just after — exercises the TTL computation via Carbon::setTestNow, not DB mutation.
 *  3. Frozen-clock: WeeklyAllowanceService::getPreviousWeekDates() without an explicit
 *     $fromDate argument returns the correct previous-week boundaries when called from a
 *     frozen "current day" rather than relying on the real system clock.
 *
 * Findings:
 *  - OvertimePay + LatePayCompensation batch types ARE wired for bundling via BatchPaymentBundleService::addSource.
 *    Both implement PayableBatchSource and have eligible adapters. Full lifecycle coverage in their respective test files.
 *  - No payment-token-expiry frozen-clock test existed prior to this file.
 *  - WeeklyAllowanceService::getPreviousWeekDates() without arguments correctly uses Carbon::now(),
 *    which respects Carbon::setTestNow — so frozen-clock tests are meaningful.
 */

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentBundleSignatureService;
use App\Services\Hris\WeeklyAllowanceService;
use Carbon\Carbon;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BatchPaymentTestFactory as F;

beforeEach(function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->signatures = app(PaymentBundleSignatureService::class);

    $this->creator = User::factory()->create();
    $this->employee = F::employee();
});

afterEach(function () {
    Carbon::setTestNow();
});

// ─── Net-new #1 ──────────────────────────────────────────────────────────────
// An ineligible source rejected mid-bundle must not corrupt the bundle total or
// the status of a co-source that was already successfully added.
it('rejecting an ineligible co-source leaves the bundle total and valid source unchanged', function () {
    $bundle = $this->bundles->create($this->creator);

    $good = F::overtime($this->employee, '75000.00');
    $goodItem = $this->batchBundles->addSource($bundle, $good, $this->creator);

    // Mark the second batch ineligible before trying to add it.
    $bad = F::latePay($this->employee, '50000.00');
    $bad->update(['payment_status' => 'paid']);

    expect(fn () => $this->batchBundles->addSource($bundle->fresh(), $bad->fresh(), $this->creator))
        ->toThrow(RuntimeException::class, 'not payment eligible');

    // Rejection must not roll back the first item or change its status.
    $freshBundle = $bundle->fresh();
    expect(bccomp((string) $freshBundle->total_amount, '75000.00', 2))->toBe(0);
    expect($goodItem->fresh()->status)->toBe(\App\Models\Hris\PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
    expect($good->fresh()->payment_status)->toBe(\App\Models\Hris\PaymentBundleItem::PAYMENT_STATUS_BUNDLED);
});

// ─── Net-new #2 ──────────────────────────────────────────────────────────────
// Frozen-clock: signing-link token expiry is computed from now() at createSigningLink time.
// A token created at T=0 must be VALID at T+71h59m and EXPIRED at T+72h01m.
// The existing PaymentBundleSigningLinkTest only uses forceFill to set expiry in the past —
// it never uses travelTo to verify that the TTL arithmetic is correct from a frozen origin.
it('signing token is valid just before the 72-hour TTL and expired just after (frozen-clock)', function () {
    Carbon::setTestNow(Carbon::parse('2026-06-03 08:00:00'));

    $bundle = PaymentBundle::factory()->create([
        'canonical_payload_hash' => str_repeat('b', 64),
    ]);
    $sig = PaymentBundleSignature::factory()->create([
        'payment_bundle_id' => $bundle->id,
        'signature_slot' => PaymentBundleSignatureSlot::FINANCE_SIGNER_1,
    ]);
    $signer = tap(User::factory()->create())->assignRole('finance_signer');

    $url = $this->signatures->createSigningLink($sig, $signer);
    parse_str(parse_url($url, PHP_URL_QUERY), $q);
    $raw = $q['token'];

    // T + 71h59m — still within 72 h window
    Carbon::setTestNow(Carbon::parse('2026-06-03 08:00:00')->addHours(71)->addMinutes(59));
    expect($this->signatures->tokenStatus($sig->fresh(), $raw))->toBe('valid');

    // T + 72h01m — 1 minute past the window
    Carbon::setTestNow(Carbon::parse('2026-06-03 08:00:00')->addHours(72)->addMinutes(1));
    expect($this->signatures->tokenStatus($sig->fresh(), $raw))->toBe('expired');
});

// ─── Net-new #3 ──────────────────────────────────────────────────────────────
// Frozen-clock: WeeklyAllowanceService::getPreviousWeekDates() with NO explicit $fromDate
// delegates to Carbon::now() internally. Freezing the clock at a known Wednesday and at a
// Monday boundary verifies the date arithmetic without depending on the real system date.
//
// Behavior note: the method returns the "most recent past Monday" via ->previous(MONDAY).
// From a Wednesday (mid-week), that is the Monday of the SAME week (not the prior week).
// From a Monday, it goes back to the prior week's Monday (via the subWeek branch).
it('getPreviousWeekDates with no argument returns correct boundaries from a frozen Wednesday', function () {
    // 2026-06-03 is a Wednesday. ->previous(MONDAY) lands on 2026-06-01 (same week's Monday).
    Carbon::setTestNow(Carbon::parse('2026-06-03 12:00:00'));

    $dates = WeeklyAllowanceService::getPreviousWeekDates();

    expect($dates['start']->format('Y-m-d'))->toBe('2026-06-01');
    expect($dates['end']->format('Y-m-d'))->toBe('2026-06-05');
    expect($dates['start']->dayOfWeek)->toBe(Carbon::MONDAY);
    expect($dates['end']->dayOfWeek)->toBe(Carbon::FRIDAY);
    expect($dates['payment']->dayOfWeek)->toBe(Carbon::TUESDAY);
});

it('getPreviousWeekDates with no argument returns correct boundaries from a frozen Monday', function () {
    // 2026-06-08 is a Monday. The Monday branch goes back one week → 2026-06-01.
    Carbon::setTestNow(Carbon::parse('2026-06-08 09:00:00'));

    $dates = WeeklyAllowanceService::getPreviousWeekDates();

    expect($dates['start']->format('Y-m-d'))->toBe('2026-06-01');
    expect($dates['end']->format('Y-m-d'))->toBe('2026-06-05');
    expect($dates['start']->dayOfWeek)->toBe(Carbon::MONDAY);
    expect($dates['end']->dayOfWeek)->toBe(Carbon::FRIDAY);
    expect((int) $dates['start']->diffInDays($dates['end']))->toBe(4);
});

it('getPreviousWeekDates returns a different week when the clock moves forward one week', function () {
    // From Wed June 3 → start = June 1 (Mon of same week)
    Carbon::setTestNow(Carbon::parse('2026-06-03 12:00:00'));
    $first = WeeklyAllowanceService::getPreviousWeekDates();

    // From Wed June 10 (one week later) → start = June 8 (Mon of same week)
    Carbon::setTestNow(Carbon::parse('2026-06-10 12:00:00'));
    $second = WeeklyAllowanceService::getPreviousWeekDates();

    expect($second['start']->format('Y-m-d'))->not->toBe($first['start']->format('Y-m-d'));
    expect($second['start']->gt($first['start']))->toBeTrue();
});
