<?php

/**
 * Phase 11 — Money integrity: net-new correctness cases + extended no-float guard.
 *
 * COVERAGE CLASSIFICATION — already covered elsewhere (NOT duplicated here):
 *
 *   amount stored as decimal string '5000000.00'             [PaymentRequestLifecycleTest L37]
 *   bank encryption + last4                                   [PaymentRequestLifecycleTest L41]
 *   PR bccomp total after addPaymentRequest                   [PaymentBundleServiceTest L106]
 *   WAB bccomp total after addWeeklyAllowanceBatch            [PaymentBundleServiceTest L130]
 *   multi-item sum (PR + WAB = 8_000_000)                    [PaymentBundleServiceTest L138]
 *   remove reduces total                                       [PaymentBundleServiceTest L153]
 *   batch total (weekly+overtime+latepay = 250_000)          [BatchPaymentBundleServiceTest L45]
 *   zero amount GAP-C characterization                        [ManualPaymentRequestFlowTest L286]
 *   negative amount GAP-C characterization                    [ManualPaymentRequestFlowTest L302]
 *   large amount (100B IDR)                                   [ManualPaymentRequestFlowTest L259]
 *   IDR display does not mutate stored value                  [ManualPaymentRequestFlowTest L266]
 *   ER API returns amount as string '150000.00'               [ExpenseRequestApiTest L41]
 *   No-float guard for BatchPaymentBundleService + BatchPayments/* + snapshot support
 *                                                             [BatchPaymentNoFloatGuardTest]
 *
 * NET-NEW cases in this file:
 *   1. bundle.total_amount == string-equal sum of its PR item amounts (bcadd loop)
 *   2. bundle.total_amount == string-equal sum of batch-source item amounts
 *   3. item.amount and bundle.total_amount both carry exactly 2 decimal places (scale consistency)
 *   4. Reimbursement API /api/hris/reimbursements returns amount as a STRING — currently FAILS
 *      because ReimbursementController uses `(float)` cast (BUG-8, deferred)
 *   5. Extended no-float guard: services + models NOT covered by BatchPaymentNoFloatGuardTest
 *      - BUG-3 allowlisted: CanonicalPayloadHashService::money() line ~108
 *      - BUG-8 allowlisted: PaymentRequestService::varianceMetadata() line ~118
 *      - ReimbursementController BUG-8b allowlisted: lines 37, 76
 *
 * DEFERRED bugs (do NOT fix app code):
 *   BUG-3  CanonicalPayloadHashService::money() uses (float) — precision loss above 2^53
 *   BUG-7  createManual accepts zero/negative amounts (characterization in ManualPaymentRequestFlowTest)
 *   BUG-8  PaymentRequestService::varianceMetadata uses (float) bcdiv for ratio comparison
 *   BUG-8b ReimbursementController returns amount as (float) in JSON response
 */

use App\Models\Hris\Employee;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\Reimbursement;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\Hris\WeeklyAllowancePayment;
use App\Models\User;
use App\Services\Hris\BatchPaymentBundleService;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

// ─── helpers (p11_ prefix) ───────────────────────────────────────────────────

function p11_approvedPr(User $creator, User $coo, int $amount = 5_000_000): PaymentRequest
{
    $svc = app(PaymentRequestService::class);
    $pr = $svc->createManual($creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT P11 Vendor',
        'bank_name' => 'BCA',
        'bank_account_number' => '1234567890',
        'amount' => $amount,
        'currency' => 'IDR',
        'description' => 'p11 test',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, $coo);

    return $pr->fresh();
}

function p11_eligibleWab(string $amount = '2000000.00'): WeeklyAllowanceBatch
{
    $employee = Employee::factory()->create([
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'P11 Employee',
    ]);

    $batch = WeeklyAllowanceBatch::create([
        'week_start_date' => '2026-05-25',
        'week_end_date' => '2026-05-31',
        'payment_date' => '2026-06-02',
        'status' => WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE,
        'total_employees' => 1,
        'total_amount' => $amount,
    ]);

    WeeklyAllowancePayment::create([
        'weekly_allowance_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'daily_rate' => $amount,
        'days_present' => 1,
        'total_amount' => $amount,
        'bank_name' => 'BCA',
        'bank_account_number' => '9876543210',
        'bank_account_holder' => 'P11 Employee',
        'status' => WeeklyAllowancePayment::STATUS_APPROVED,
    ]);

    return $batch;
}

// ─── test suite ──────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->bundles = app(PaymentBundleService::class);
    $this->batchBundles = app(BatchPaymentBundleService::class);
    $this->creator = User::factory()->create();
    $this->coo = User::factory()->create();
});

// 1. Bundle total == exact bcadd sum of its PR item amounts
it('bundle total_amount equals exact string-sum of its PR item amounts (bcadd)', function () {
    $bundle = $this->bundles->create($this->creator);

    $pr1 = p11_approvedPr($this->creator, $this->coo, 3_750_000);
    $pr2 = p11_approvedPr($this->creator, $this->coo, 1_250_000);

    $item1 = $this->bundles->addPaymentRequest($bundle, $pr1, $this->creator);
    $item2 = $this->bundles->addPaymentRequest($bundle->fresh(), $pr2, $this->creator);

    $computedSum = bcadd((string) $item1->amount, (string) $item2->amount, 2);
    $bundleTotal = (string) $bundle->fresh()->total_amount;

    expect(bccomp($bundleTotal, $computedSum, 2))->toBe(0,
        "bundle total [{$bundleTotal}] != sum of items [{$computedSum}]"
    );
    // Also verify each item amount matches the source PR amount exactly
    expect(bccomp((string) $item1->amount, (string) $pr1->amount, 2))->toBe(0);
    expect(bccomp((string) $item2->amount, (string) $pr2->amount, 2))->toBe(0);
});

// 2. Bundle total == exact bcadd sum of batch-source item amounts
it('bundle total_amount equals exact string-sum of batch-source item amounts (bcadd)', function () {
    $bundle = $this->bundles->create($this->creator);

    $wab1 = p11_eligibleWab('1500000.00');
    $wab2 = p11_eligibleWab('2500000.00');

    $item1 = $this->batchBundles->addSource($bundle, $wab1, $this->creator);
    $item2 = $this->batchBundles->addSource($bundle->fresh(), $wab2, $this->creator);

    $computedSum = bcadd((string) $item1->amount, (string) $item2->amount, 2);
    $bundleTotal = (string) $bundle->fresh()->total_amount;

    expect(bccomp($bundleTotal, $computedSum, 2))->toBe(0,
        "bundle total [{$bundleTotal}] != sum of batch items [{$computedSum}]"
    );
    // Source batch total_amount must match what was stored on the item
    expect(bccomp((string) $item1->amount, (string) $wab1->total_amount, 2))->toBe(0);
    expect(bccomp((string) $item2->amount, (string) $wab2->total_amount, 2))->toBe(0);
});

// 3. Decimal scale consistency: PR, item, and bundle all carry exactly 2dp
it('amount decimal scale is consistent (exactly 2 dp) across PR, bundle item, and bundle total', function () {
    $pr = p11_approvedPr($this->creator, $this->coo, 7_777_777);
    $bundle = $this->bundles->create($this->creator);
    $item = $this->bundles->addPaymentRequest($bundle, $pr, $this->creator);

    $prAmount = (string) $pr->fresh()->amount;
    $itemAmount = (string) $item->fresh()->amount;
    $bundleTotal = (string) $bundle->fresh()->total_amount;

    // Each value must match /^\d+\.\d{2}$/ — exactly 2 decimal places
    expect($prAmount)->toMatch('/^\d+\.\d{2}$/', "PR amount [{$prAmount}] is not 2dp");
    expect($itemAmount)->toMatch('/^\d+\.\d{2}$/', "item amount [{$itemAmount}] is not 2dp");
    expect($bundleTotal)->toMatch('/^\d+\.\d{2}$/', "bundle total [{$bundleTotal}] is not 2dp");

    // All three must agree
    expect(bccomp($prAmount, $itemAmount, 2))->toBe(0);
    expect(bccomp($prAmount, $bundleTotal, 2))->toBe(0);
});

// 4. Reimbursement API returns amount as STRING (BUG-8b fixed)
//    Cast removed from ReimbursementController::index() and ::store().
//    Eloquent decimal:2 cast yields the string directly.
it('Reimbursement API /api/hris/reimbursements returns amount as a string', function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $employee = Employee::factory()->create();
    Reimbursement::create([
        'employee_id' => $employee->id,
        'type' => 'medical',
        'amount' => '350000.00',
        'status' => 'pending',
    ]);

    Sanctum::actingAs($employee->user);
    $res = $this->getJson('/api/hris/reimbursements')->assertOk();

    $rawAmount = $res->json('data.0.amount');

    expect(is_string($rawAmount))->toBeTrue(
        'BUG-8b fixed: ReimbursementController must return amount as string. '
        .'Actual PHP type: '.gettype($rawAmount).', value: '.var_export($rawAmount, true)
    );
    expect($rawAmount)->toBeString()->toBe('350000.00');
});

// 5. ER API still returns amount as string (regression guard — the correct one)
it('Expense Request API /api/hris/expense-requests returns amount as a string, not a float', function () {
    $this->seed(FinanceRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $employee = Employee::factory()->create();
    Sanctum::actingAs($employee->user);

    $res = $this->postJson('/api/hris/expense-requests', [
        'category' => 'travel',
        'title' => 'Flight P11',
        'amount' => 4_500_000,
        'expense_date' => '2026-06-01',
        'payment_mode' => 'employee_reimbursement',
    ])->assertCreated();

    $amount = $res->json('data.amount');
    expect($amount)->toBeString('ER API must return amount as string, got: '.gettype($amount));
    expect($amount)->toBe('4500000.00');
});

// 6. Extended no-float guard (services + models NOT covered by BatchPaymentNoFloatGuardTest)
//
// BatchPaymentNoFloatGuardTest already covers:
//   BatchPaymentBundleService, Services/Hris/BatchPayments/*, Support/Finance/PaymentBundleSnapshot,
//   Support/Finance/BatchPaymentSnapshotCollection, Support/Finance/MoneyString
//
// This guard covers the remaining finance money files:
//   PaymentBundleService, PaymentBundlePaymentService, PaymentBundleSignatureService,
//   CanonicalPayloadHashService, PaymentRequestService, and the finance models.
//
// KNOWN VIOLATIONS (allowlisted — deferred bugs, do NOT fix):
//
//   BUG-3: CanonicalPayloadHashService::money() line ~108
//          `number_format((float) $v, 2, '.', '')`
//          Known since Phase 6. Fix: replace with bcadd($v, '0', 2).
//
//   BUG-8: PaymentRequestService::varianceMetadata() line ~118
//          `(float) bcdiv($absDiff, (string) $er->amount, 6)`
//          Used only as a ratio comparison against the constant 0.10; the precision
//          of the ratio does not affect stored money amounts. Low risk in practice
//          but still violates the no-float rule in money handling code.
//          Fix: replace with bccomp(bcdiv(...), '0.10', 6) >= 0.
//
//   BUG-8b: ReimbursementController lines 37 and 76
//          `(float) $r->amount` in JSON serialisation — covered in test #4 above.
//          Not in scope of this static guard (it's a controller, not a service/model).

it('finance service and model files have no forbidden float casts — BUG-3 and BUG-8 allowlisted', function () {
    $serviceFiles = [
        app_path('Services/Hris/PaymentBundleService.php'),
        app_path('Services/Hris/PaymentBundlePaymentService.php'),
        app_path('Services/Hris/PaymentBundleSignatureService.php'),
        app_path('Services/Hris/CanonicalPayloadHashService.php'),
        app_path('Services/Hris/PaymentRequestService.php'),
    ];

    $modelFiles = [
        app_path('Models/Hris/PaymentRequest.php'),
        app_path('Models/Hris/PaymentBundle.php'),
        app_path('Models/Hris/PaymentBundleItem.php'),
    ];

    $allFiles = array_merge($serviceFiles, $modelFiles);

    // Allowlist: maps file basename → maximum permitted float-cast count WITH justification.
    // Any file NOT in this list must have ZERO occurrences.
    // Any file IN this list must have EXACTLY the declared count — more is a new violation.
    $allowlist = [
        // BUG-3: CanonicalPayloadHashService::money() — `number_format((float) $v, 2, '.', '')`
        // Deferred. Fix: bcadd($v, '0', 2). Reference: docs/progress/finance-center-e2e-tests.md § BUG-3
        'CanonicalPayloadHashService.php' => 1,

        // BUG-8: PaymentRequestService::varianceMetadata() — `(float) bcdiv(...)` for ratio flag
        // Deferred. Does not affect stored amounts but violates no-float rule. Fix: use bccomp.
        // Reference: Phase 11 findings.
        'PaymentRequestService.php' => 1,
    ];

    $pattern = '/\(float\)|floatval\s*\(/';
    $violations = [];

    foreach ($allFiles as $path) {
        $basename = basename($path);
        $contents = file_get_contents($path);

        preg_match_all($pattern, $contents, $matches);
        $count = count($matches[0]);

        $allowedCount = $allowlist[$basename] ?? 0;

        if ($count > $allowedCount) {
            $violations[] = sprintf(
                '%s: found %d float cast(s), allowlist permits %d. NEW violation — must be fixed or added to allowlist with a BUG reference.',
                $basename,
                $count,
                $allowedCount
            );
        } elseif ($count < $allowedCount) {
            $violations[] = sprintf(
                '%s: found %d float cast(s), but allowlist expects %d. The known violation may have been fixed — update the allowlist.',
                $basename,
                $count,
                $allowedCount
            );
        }
    }

    expect($violations)->toBeEmpty(
        "Float cast violations in finance money files:\n  - ".implode("\n  - ", $violations)
    );
});
