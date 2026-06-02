<?php

namespace App\Services\Hris;

use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PaymentBundle assembly (Phase 3): create a draft bundle and add/remove approved money-out
 * sources. Adding freezes a deterministic, encrypted source snapshot per item and flips the
 * source into in_payment_bundle under a row lock so a source can live in at most one active bundle.
 */
class PaymentBundleService
{
    public function __construct(protected FinanceAuditLogger $audit) {}

    public function create(User $creator, array $attrs = []): PaymentBundle
    {
        return DB::transaction(function () use ($creator, $attrs) {
            $bundle = PaymentBundle::create([
                'bundle_number' => $this->generateNumber(),
                'public_verification_code' => $this->generateVerificationCode(),
                'title' => $attrs['title'] ?? null,
                'status' => PaymentBundle::STATUS_DRAFT,
                'bundle_version' => 1,
                'period_start' => $attrs['period_start'] ?? null,
                'period_end' => $attrs['period_end'] ?? null,
                'total_amount' => 0,
                'currency' => $attrs['currency'] ?? 'IDR',
                'created_by' => $creator->id,
            ]);

            $this->audit->log($bundle, 'payment_bundle.created', $creator);

            return $bundle;
        });
    }

    public function addPaymentRequest(PaymentBundle $bundle, PaymentRequest $pr, User $actor): PaymentBundleItem
    {
        return DB::transaction(function () use ($bundle, $pr, $actor) {
            if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                throw new \RuntimeException('Only a draft bundle can receive items.');
            }

            $pr = PaymentRequest::query()->lockForUpdate()->findOrFail($pr->id);

            if (! in_array($pr->status, [PaymentRequest::STATUS_APPROVED_BY_COO, PaymentRequest::STATUS_APPROVED_FOR_BUNDLE], true)) {
                throw new \RuntimeException('PR is not approved/eligible for bundling.');
            }

            if (PaymentBundleItem::where('source_type', PaymentBundleItem::SOURCE_PAYMENT_REQUEST)
                ->where('source_id', $pr->id)
                ->where('status', PaymentBundleItem::STATUS_ACTIVE)
                ->exists()) {
                throw new \RuntimeException('This payment request is already in an active bundle.');
            }

            $snapshot = [
                'source_type' => PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
                'source_id' => $pr->id,
                'payment_request_id' => $pr->id,
                'payment_request_number' => $pr->payment_request_number,
                'payee_type' => $pr->payee_type,
                'payee_name' => $pr->payee_name,
                'bank_name' => $pr->bank_name,
                'bank_account_last4' => $pr->bank_account_last4,
                'bank_account_number' => $pr->bank_account_number,
                'amount' => (string) $pr->amount,
                'currency' => $pr->currency,
                'description' => $pr->description,
                'withholding_required' => $pr->withholding_required,
                'withholding_amount' => $pr->withholding_amount !== null ? (string) $pr->withholding_amount : null,
                'approval_snapshot_hash' => $pr->approval_snapshot_hash,
            ];

            $item = PaymentBundleItem::create([
                'payment_bundle_id' => $bundle->id,
                'source_type' => PaymentBundleItem::SOURCE_PAYMENT_REQUEST,
                'source_id' => $pr->id,
                'payment_request_id' => $pr->id,
                'description' => $pr->description,
                'payee_name' => $pr->payee_name,
                'payee_bank_name' => $pr->bank_name,
                'payee_bank_account_last4' => $pr->bank_account_last4,
                'amount' => $pr->amount,
                'currency' => $pr->currency,
                'source_snapshot_json' => $snapshot,
                'source_hash' => $this->snapshotHash($snapshot),
                'status' => PaymentBundleItem::STATUS_ACTIVE,
            ]);

            $pr->update(['status' => PaymentRequest::STATUS_IN_PAYMENT_BUNDLE]);

            $this->recomputeTotal($bundle);

            $this->audit->log($bundle, 'payment_bundle.item_added', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'amount' => (string) $item->amount,
            ]]);

            return $item;
        });
    }

    public function addWeeklyAllowanceBatch(PaymentBundle $bundle, WeeklyAllowanceBatch $wab, User $actor): PaymentBundleItem
    {
        return DB::transaction(function () use ($bundle, $wab, $actor) {
            if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                throw new \RuntimeException('Only a draft bundle can receive items.');
            }

            $wab = WeeklyAllowanceBatch::query()->lockForUpdate()->findOrFail($wab->id);

            if ($wab->status !== WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE) {
                throw new \RuntimeException('Weekly allowance batch is not eligible for bundling.');
            }

            if (PaymentBundleItem::where('source_type', PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH)
                ->where('source_id', $wab->id)
                ->where('status', PaymentBundleItem::STATUS_ACTIVE)
                ->exists()) {
                throw new \RuntimeException('This weekly allowance batch is already in an active bundle.');
            }

            $snapshot = [
                'source_type' => PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
                'source_id' => $wab->id,
                'payee_name' => 'Weekly Allowance — '.$wab->week_label,
                'amount' => (string) $wab->total_amount,
                'currency' => 'IDR',
                'week_start_date' => optional($wab->week_start_date)->toDateString(),
                'week_end_date' => optional($wab->week_end_date)->toDateString(),
                'total_employees' => $wab->total_employees,
                'description' => 'Weekly allowance batch payout',
            ];

            $item = PaymentBundleItem::create([
                'payment_bundle_id' => $bundle->id,
                'source_type' => PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH,
                'source_id' => $wab->id,
                'description' => $snapshot['description'],
                'payee_name' => $snapshot['payee_name'],
                'payee_bank_name' => null,
                'payee_bank_account_last4' => null,
                'amount' => $wab->total_amount,
                'currency' => 'IDR',
                'source_snapshot_json' => $snapshot,
                'source_hash' => $this->snapshotHash($snapshot),
                'status' => PaymentBundleItem::STATUS_ACTIVE,
            ]);

            $wab->update(['status' => WeeklyAllowanceBatch::STATUS_IN_PAYMENT_BUNDLE]);

            $this->recomputeTotal($bundle);

            $this->audit->log($bundle, 'payment_bundle.item_added', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
                'amount' => (string) $item->amount,
            ]]);

            return $item;
        });
    }

    public function removeItem(PaymentBundleItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor) {
            $bundle = $item->bundle()->lockForUpdate()->firstOrFail();

            if ($bundle->status !== PaymentBundle::STATUS_DRAFT) {
                throw new \RuntimeException('Items can only be removed from a draft bundle.');
            }

            if ($item->source_type === PaymentBundleItem::SOURCE_PAYMENT_REQUEST) {
                $pr = PaymentRequest::find($item->source_id);
                $pr?->update(['status' => PaymentRequest::STATUS_APPROVED_FOR_BUNDLE]);
            } elseif ($item->source_type === PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH) {
                $wab = WeeklyAllowanceBatch::find($item->source_id);
                $wab?->update(['status' => WeeklyAllowanceBatch::STATUS_ELIGIBLE_FOR_BUNDLE]);
            }

            $item->update(['status' => PaymentBundleItem::STATUS_RELEASED]);

            $this->recomputeTotal($bundle);

            $this->audit->log($bundle, 'payment_bundle.item_removed', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
            ]]);
            $this->audit->log($bundle, 'payment_bundle.item_released', $actor, ['after' => [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'source_id' => $item->source_id,
            ]]);
        });
    }

    public function recomputeTotal(PaymentBundle $bundle): void
    {
        $total = '0';
        foreach ($bundle->items()->where('status', PaymentBundleItem::STATUS_ACTIVE)->get() as $item) {
            $total = bcadd($total, (string) $item->amount, 2);
        }

        $bundle->update(['total_amount' => $total]);
    }

    private function generateNumber(): string
    {
        $prefix = 'PB-'.now()->format('Ym').'-';

        $last = PaymentBundle::where('bundle_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('bundle_number')
            ->value('bundle_number');

        $next = $last ? ((int) substr($last, -4)) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function generateVerificationCode(): string
    {
        do {
            $code = Str::random(48);
        } while (PaymentBundle::where('public_verification_code', $code)->exists());

        return $code;
    }

    /**
     * Deterministic sha256 over the plaintext business fields (recursive key-sort + canonical JSON),
     * so drift between an authorized item and its live source is detectable in later phases.
     */
    private function snapshotHash(array $data): string
    {
        $this->ksortRecursive($data);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function ksortRecursive(array &$data): void
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
        unset($value);

        ksort($data);
    }
}
