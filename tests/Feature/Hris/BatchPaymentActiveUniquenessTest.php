<?php

use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\User;
use Illuminate\Database\QueryException;

it('enforces a DB-level uniqueness guard for active bundle sources', function () {
    $actor = User::factory()->create();
    $bundleA = PaymentBundle::factory()->create(['created_by' => $actor->id]);
    $bundleB = PaymentBundle::factory()->create(['created_by' => $actor->id]);

    PaymentBundleItem::create([
        'payment_bundle_id' => $bundleA->id,
        'source_type' => PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
        'source_id' => 123,
        'description' => 'Weekly allowance',
        'payee_name' => 'Weekly Allowance',
        'amount' => '100000.00',
        'currency' => 'IDR',
        'source_snapshot_json' => ['snapshot_version' => 1],
        'source_hash' => str_repeat('a', 64),
        'status' => PaymentBundleItem::PAYMENT_STATUS_BUNDLED,
    ]);

    expect(fn () => PaymentBundleItem::create([
        'payment_bundle_id' => $bundleB->id,
        'source_type' => PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
        'source_id' => 123,
        'description' => 'Weekly allowance',
        'payee_name' => 'Weekly Allowance',
        'amount' => '100000.00',
        'currency' => 'IDR',
        'source_snapshot_json' => ['snapshot_version' => 1],
        'source_hash' => str_repeat('b', 64),
        'status' => PaymentBundleItem::PAYMENT_STATUS_BUNDLED,
    ]))->toThrow(QueryException::class);

    PaymentBundleItem::create([
        'payment_bundle_id' => $bundleB->id,
        'source_type' => PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
        'source_id' => 123,
        'description' => 'Weekly allowance released',
        'payee_name' => 'Weekly Allowance',
        'amount' => '100000.00',
        'currency' => 'IDR',
        'source_snapshot_json' => ['snapshot_version' => 1],
        'source_hash' => str_repeat('c', 64),
        'status' => PaymentBundleItem::PAYMENT_STATUS_RELEASED,
    ]);

    expect(PaymentBundleItem::count())->toBe(2);
});
