<?php

namespace App\Services\Hris;

use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use App\Models\Hris\PaymentRequest;
use App\Models\Hris\WeeklyAllowanceBatch;
use App\Models\User;
use App\Support\Finance\PaymentBundleSnapshot;
use Illuminate\Support\Facades\DB;

/**
 * Phase 10 — Payment execution from the frozen snapshot. Once a bundle is authorized + signed,
 * finance pays each item MANUALLY using the values frozen at assembly time. This service is
 * snapshot-authoritative: payment details are read ONLY from PaymentBundleItem::source_snapshot_json,
 * never from the live PR/WAB, so a post-authorization edit to a source cannot change what gets paid.
 * Live-vs-frozen drift is detected and audited but never blocks (the signed snapshot is the contract).
 */
class PaymentBundlePaymentService
{
    public function __construct(protected FinanceAuditLogger $audit) {}

    /**
     * READ-ONLY payment instructions for finance, built strictly from each item's frozen, decrypted
     * snapshot. The returned bank_account_number is the FULL account stored in the snapshot and is
     * sensitive: any UI/API exposure must be permission-gated in Phase 13. This service only returns
     * the frozen data; it performs no gating itself.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paymentInstructions(PaymentBundle $bundle): array
    {
        $items = $bundle->items()
            ->whereIn('status', [PaymentBundleItem::STATUS_ACTIVE, PaymentBundleItem::STATUS_PAYMENT_RECORDING])
            ->get();

        return $items->map(function (PaymentBundleItem $item) {
            $snap = $item->source_snapshot_json;

            return [
                'item_id' => $item->id,
                'source_type' => $item->source_type,
                'payee_name' => $snap['payee_name'],
                'bank_name' => $snap['bank_name'] ?? null,
                'bank_account_number' => $snap['bank_account_number'] ?? null,
                'amount' => $snap['amount'],
                'currency' => $snap['currency'],
                'description' => $snap['description'] ?? null,
                'drift' => $this->detectDrift($item),
            ];
        })->all();
    }

    /**
     * Rebuild the snapshot from the CURRENT live source and compare its hash to the frozen
     * source_hash. Returns true if they differ (the live source changed since authorization) or if
     * the live source no longer exists. Pure read — mutates nothing.
     */
    public function detectDrift(PaymentBundleItem $item): bool
    {
        $live = $this->liveSnapshot($item);

        if ($live === null) {
            return true;
        }

        return PaymentBundleSnapshot::hash($live) !== $item->source_hash;
    }

    /**
     * Move an authorized + signed bundle into payment_recording and hand finance the frozen
     * instructions to pay manually. Detects (and audits) per-item drift without blocking. Does NOT
     * mark anything paid and does NOT touch payment proof — that is Phase 11.
     *
     * @return array<int, array<string, mixed>>
     */
    public function beginPaymentRecording(PaymentBundle $bundle, User $actor): array
    {
        return DB::transaction(function () use ($bundle, $actor) {
            $bundle = PaymentBundle::query()->lockForUpdate()->findOrFail($bundle->id);

            if ($bundle->status !== PaymentBundle::STATUS_AUTHORIZED_FOR_PAYMENT) {
                throw new \RuntimeException('Only an authorized bundle can enter payment recording.');
            }

            if ($bundle->signed_pdf_attachment_id === null) {
                throw new \RuntimeException('A signed authorization document is required before payment.');
            }

            $activeItems = $bundle->items()->where('status', PaymentBundleItem::STATUS_ACTIVE)->get();

            foreach ($activeItems as $item) {
                if ($this->detectDrift($item)) {
                    $live = $this->liveSnapshot($item);
                    $this->audit->log($bundle, 'payment_bundle.source_drift_detected', $actor, ['after' => [
                        'item_id' => $item->id,
                        'source_type' => $item->source_type,
                        'source_id' => $item->source_id,
                        'stored_hash' => $item->source_hash,
                        'live_hash' => $live !== null ? PaymentBundleSnapshot::hash($live) : null,
                    ]]);
                }
            }

            $bundle->update(['status' => PaymentBundle::STATUS_PAYMENT_RECORDING]);

            foreach ($activeItems as $item) {
                $item->update(['status' => PaymentBundleItem::STATUS_PAYMENT_RECORDING]);
            }

            $this->audit->log($bundle, 'payment_bundle.payment_recording_started', $actor);

            return $this->paymentInstructions($bundle->refresh());
        });
    }

    /** The live source rebuilt as a snapshot array, or null if the source row no longer exists. */
    private function liveSnapshot(PaymentBundleItem $item): ?array
    {
        if ($item->source_type === PaymentBundleItem::SOURCE_PAYMENT_REQUEST) {
            $pr = PaymentRequest::find($item->source_id);

            return $pr !== null ? PaymentBundleSnapshot::forPaymentRequest($pr) : null;
        }

        if ($item->source_type === PaymentBundleItem::SOURCE_WEEKLY_ALLOWANCE_BATCH) {
            $wab = WeeklyAllowanceBatch::find($item->source_id);

            return $wab !== null ? PaymentBundleSnapshot::forWeeklyAllowanceBatch($wab) : null;
        }

        return null;
    }
}
