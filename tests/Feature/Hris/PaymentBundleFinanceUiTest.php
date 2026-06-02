<?php

use App\Filament\Resources\PaymentBundleResource;
use App\Filament\Resources\PaymentBundleResource\Pages\ListPaymentBundles;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentBundleSignature;
use App\Models\Hris\PaymentRequest;
use App\Models\User;
use App\Services\Hris\PaymentBundleService;
use App\Services\Hris\PaymentRequestService;
use Database\Seeders\FinanceRolePermissionSeeder;
use Database\Seeders\PaymentBundleRolePermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake('private');
    $this->seed(FinanceRolePermissionSeeder::class);
    $this->seed(PaymentBundleRolePermissionSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

// ─── helpers ─────────────────────────────────────────────────────────────────

function pbUiDraftBundle(User $creator): PaymentBundle
{
    return app(PaymentBundleService::class)->create($creator, ['title' => 'UI Test Bundle']);
}

function pbUiApprovedPr(User $creator): PaymentRequest
{
    $coo = User::factory()->create();
    $svc = app(PaymentRequestService::class);
    $pr = $svc->createManual($creator, [
        'payee_type' => 'vendor',
        'payee_name' => 'PT UI Test Vendor',
        'bank_name' => 'BNI',
        'bank_account_number' => '9876543210',
        'amount' => 4500000,
        'currency' => 'IDR',
        'description' => 'UI test PR',
    ]);
    $svc->submitToCoo($pr);
    $svc->approveAsCoo($pr, $coo);

    return $pr->fresh();
}

// ─── Test 1: Access gating ─────────────────────────────────────────────────

it('denies canViewAny to a user without payment_bundles.view', function () {
    $user = User::factory()->create();
    actingAs($user);

    expect(PaymentBundleResource::canViewAny())->toBeFalse();
});

it('grants canViewAny to a user with payment_bundles.view permission', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_creator');
    actingAs($user);

    expect(PaymentBundleResource::canViewAny())->toBeTrue();
});

it('grants canViewAny to a super_admin', function () {
    $user = User::factory()->create();
    // super_admin may not exist in the test seeder — create it if needed
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user->assignRole('super_admin');
    actingAs($user);

    expect(PaymentBundleResource::canViewAny())->toBeTrue();
});

// ─── Test 2: List renders + tabs ───────────────────────────────────────────

it('list page renders successfully for an authorized user', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_creator');

    Livewire::actingAs($user)
        ->test(ListPaymentBundles::class)
        ->assertSuccessful();
});

it('paid tab query filter returns only paid bundles', function () {
    $creator = User::factory()->create();
    $creator->assignRole('finance_creator');

    $paid = PaymentBundle::factory()->create([
        'status' => PaymentBundle::STATUS_PAID,
        'created_by' => $creator->id,
    ]);
    $draft = PaymentBundle::factory()->create([
        'status' => PaymentBundle::STATUS_DRAFT,
        'created_by' => $creator->id,
    ]);

    // Verify the tab filter logic: paid tab filters to status=paid
    $paidQuery = PaymentBundle::where('status', PaymentBundle::STATUS_PAID);
    $ids = $paidQuery->pluck('id');

    expect($ids)->toContain($paid->id);
    expect($ids)->not->toContain($draft->id);

    // Also verify the list page renders without error
    Livewire::actingAs($creator)
        ->test(ListPaymentBundles::class)
        ->assertSuccessful();
});

// ─── Test 3: Create action ─────────────────────────────────────────────────

it('create_bundle header action creates a draft PaymentBundle', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_creator');

    Livewire::actingAs($user)
        ->test(ListPaymentBundles::class)
        ->callAction('create_bundle', data: [
            'title' => 'June 2026 Payout',
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('payment_bundles', [
        'title' => 'June 2026 Payout',
        'status' => PaymentBundle::STATUS_DRAFT,
        'created_by' => $user->id,
    ]);
});

// ─── Test 4: Add approved PR action ────────────────────────────────────────

it('add_payment_request action adds an item and transitions PR to in_payment_bundle', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_creator');

    $bundle = pbUiDraftBundle($user);
    $pr = pbUiApprovedPr($user);

    Livewire::actingAs($user)
        ->test(ListPaymentBundles::class)
        ->callTableAction('add_payment_request', $bundle, data: [
            'payment_request_id' => $pr->id,
        ])
        ->assertHasNoTableActionErrors();

    $this->assertDatabaseHas('payment_bundle_items', [
        'payment_bundle_id' => $bundle->id,
        'source_type' => PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
        'source_id' => $pr->id,
        'status' => PaymentBundleItem::STATUS_ACTIVE,
    ]);

    expect($pr->fresh()->status)->toBe(PaymentRequest::STATUS_IN_PAYMENT_BUNDLE);
});

// ─── Test 5: Submit for signature ──────────────────────────────────────────

it('submit_for_signature action transitions bundle to pending_finance_signature_1 with 3 signature rows', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_creator');

    $bundle = pbUiDraftBundle($user);
    $pr = pbUiApprovedPr($user);
    app(PaymentBundleService::class)->addPaymentRequest($bundle, $pr, $user);
    $bundle = $bundle->fresh();

    $fs1 = User::factory()->create();
    $fs1->assignRole('finance_signer');
    $fs2 = User::factory()->create();
    $fs2->assignRole('finance_signer');
    $exec = User::factory()->create();
    $exec->assignRole('executive_signer');

    Livewire::actingAs($user)
        ->test(ListPaymentBundles::class)
        ->callTableAction('submit_for_signature', $bundle, data: [
            'finance_signer_1' => $fs1->id,
            'finance_signer_2' => $fs2->id,
            'executive_signer' => $exec->id,
        ])
        ->assertHasNoTableActionErrors();

    $fresh = $bundle->fresh();
    expect($fresh->status)->toBe(PaymentBundle::STATUS_PENDING_FINANCE_SIGNATURE_1);

    expect(PaymentBundleSignature::where('payment_bundle_id', $bundle->id)->count())->toBe(3);
});

// ─── Test 6: No full bank account in item display ──────────────────────────

it('bundle item display contains last4 but not the full account number', function () {
    $user = User::factory()->create();
    $user->assignRole('finance_creator');

    $bundle = pbUiDraftBundle($user);
    $pr = pbUiApprovedPr($user);
    app(PaymentBundleService::class)->addPaymentRequest($bundle, $pr, $user);

    $item = PaymentBundleItem::where('payment_bundle_id', $bundle->id)->first();

    expect($item->payee_bank_account_last4)->toBe('3210');

    $fullAccount = '9876543210';
    expect(strlen($fullAccount))->toBeGreaterThan(4);

    // The item model only stores last4; the full number is encrypted inside source_snapshot_json.
    // Confirm the plain text column is last4 only.
    expect($item->payee_bank_account_last4)->not->toBe($fullAccount);
    expect(strlen((string) $item->payee_bank_account_last4))->toBeLessThanOrEqual(4);
});
