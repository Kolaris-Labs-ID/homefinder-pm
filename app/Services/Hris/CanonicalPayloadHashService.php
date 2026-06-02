<?php

namespace App\Services\Hris;

use App\Enums\PaymentBundleSignatureSlot;
use App\Models\Hris\PaymentBundle;
use App\Models\Hris\PaymentBundleItem;
use Illuminate\Support\Facades\DB;

/**
 * Produces the deterministic canonical_payload_hash that is the cryptographic source of truth
 * for all downstream signatures and verification (Phase 4). Determinism rules:
 *   - Items sorted by source_type ASC, source_id ASC before embedding.
 *   - All money as fixed-precision "0.00" strings (never floats).
 *   - Keys recursively ksorted before JSON encoding.
 *   - JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE — no extra escaping.
 */
class CanonicalPayloadHashService
{
    public function __construct(protected FinanceAuditLogger $audit) {}

    public function build(PaymentBundle $bundle): array
    {
        $items = $bundle->activeItems()
            ->orderBy('source_type')
            ->orderBy('source_id')
            ->get();

        $signatures = $bundle->signatures()->get()->keyBy(fn ($s) => $s->signature_slot->value);

        $orderedSlots = PaymentBundleSignatureSlot::ordered();

        $requiredSlots = collect($orderedSlots)
            ->filter(fn ($slot) => $signatures->has($slot->value))
            ->map(fn ($slot) => $slot->value)
            ->values()
            ->all();

        $assignedSignerIds = [];
        foreach ($orderedSlots as $slot) {
            if ($signatures->has($slot->value)) {
                $sig = $signatures->get($slot->value);
                $assignedSignerIds[$slot->value] = $sig->assigned_user_id !== null
                    ? (int) $sig->assigned_user_id
                    : null;
            }
        }

        $itemsArray = $items->map(fn (PaymentBundleItem $item) => [
            'source_type' => $item->source_type,
            'source_id' => (int) $item->source_id,
            'source_hash' => $item->source_hash,
            'payee_name' => $item->payee_name,
            'bank_last4' => $item->payee_bank_account_last4,
            'amount' => $this->money($item->amount),
            'currency' => $item->currency,
        ])->values()->all();

        return [
            'bundle_number' => $bundle->bundle_number,
            'bundle_version' => (int) $bundle->bundle_version,
            'currency' => $bundle->currency,
            'total_amount' => $this->money($bundle->total_amount),
            'required_signature_slots' => $requiredSlots,
            'assigned_signer_ids' => $assignedSignerIds,
            'items' => $itemsArray,
        ];
    }

    public function hash(array $payload): string
    {
        $sorted = $this->ksortRecursive($payload);

        return hash('sha256', json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public function generate(PaymentBundle $bundle): array
    {
        $payload = $this->build($bundle);

        return ['payload' => $payload, 'hash' => $this->hash($payload)];
    }

    public function store(PaymentBundle $bundle): string
    {
        return DB::transaction(function () use ($bundle) {
            $payload = $this->build($bundle);
            $hash = $this->hash($payload);

            $bundle->update([
                'canonical_payload_json' => $payload,
                'canonical_payload_hash' => $hash,
            ]);

            $this->audit->log($bundle, 'payment_bundle.payload_hash_generated', null, [
                'after' => [
                    'canonical_payload_hash' => $hash,
                    'bundle_version' => $bundle->bundle_version,
                ],
            ]);

            return $hash;
        });
    }

    private function money(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    private function ksortRecursive(array $arr): array
    {
        ksort($arr);
        foreach ($arr as &$v) {
            if (is_array($v)) {
                $v = $this->ksortRecursive($v);
            }
        }

        return $arr;
    }
}
