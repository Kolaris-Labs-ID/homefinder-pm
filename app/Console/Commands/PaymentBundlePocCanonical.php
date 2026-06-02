<?php

namespace App\Console\Commands;

/**
 * Spike-phase canonical payload hash helper. Phase 4 will extract the production version
 * into App\Services\Hris\CanonicalPayloadHashService with full validation.
 */
class PaymentBundlePocCanonical
{
    /**
     * Compute canonical_payload_hash for a payment bundle payload.
     *
     * Rules:
     * - Items sorted by source_type ASC, source_id ASC (input order irrelevant).
     * - Money values formatted as fixed-precision "0.00" strings (never floats).
     * - Array keys sorted recursively before JSON encoding.
     * - JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE — no extra escaping.
     * - No timestamps, UUIDs, or random values in the canonical payload.
     */
    public static function hash(array $payload): string
    {
        $canonical = self::buildCanonical($payload);

        return hash('sha256', json_encode($canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function buildCanonical(array $payload): array
    {
        $items = $payload['ordered_items'] ?? [];

        usort($items, fn ($a, $b) =>
            [$a['source_type'], (string) $a['source_id']] <=> [$b['source_type'], (string) $b['source_id']]
        );

        $signerIds = $payload['assigned_signer_ids'] ?? [];
        sort($signerIds);

        $sigSlots = $payload['required_signature_slots'] ?? [];
        sort($sigSlots);

        $canonical = [
            'amount'                   => self::money($payload['amount'] ?? 0),
            'assigned_signer_ids'      => array_values($signerIds),
            'bank_last4'               => (string) ($payload['bank_last4'] ?? ''),
            'bundle_number'            => (string) ($payload['bundle_number'] ?? ''),
            'bundle_version'           => (int) ($payload['bundle_version'] ?? 1),
            'currency'                 => (string) ($payload['currency'] ?? 'IDR'),
            'ordered_items'            => array_values(array_map(fn ($item) => self::ksortRecursive([
                'amount'      => self::money($item['amount'] ?? 0),
                'bank_last4'  => (string) ($item['bank_last4'] ?? ''),
                'currency'    => (string) ($item['currency'] ?? 'IDR'),
                'payee_name'  => (string) ($item['payee_name'] ?? ''),
                'source_hash' => (string) ($item['source_hash'] ?? ''),
                'source_id'   => (string) ($item['source_id'] ?? ''),
                'source_type' => (string) ($item['source_type'] ?? ''),
            ]), $items)),
            'payee_name'               => (string) ($payload['payee_name'] ?? ''),
            'required_signature_slots' => array_values($sigSlots),
            'source_hash'              => (string) ($payload['source_hash'] ?? ''),
            'source_id'                => (string) ($payload['source_id'] ?? ''),
            'source_type'              => (string) ($payload['source_type'] ?? ''),
            'total_amount'             => self::money($payload['total_amount'] ?? 0),
        ];

        return self::ksortRecursive($canonical);
    }

    private static function money(mixed $v): string
    {
        return number_format((float) $v, 2, '.', '');
    }

    private static function ksortRecursive(array $arr): array
    {
        ksort($arr);
        foreach ($arr as &$v) {
            if (is_array($v)) {
                $v = self::ksortRecursive($v);
            }
        }

        return $arr;
    }
}
