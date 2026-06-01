<?php

namespace App\Services\Hris;

use App\Models\Hris\FinanceAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Writes hash-chained, append-only finance audit entries (EXPENSE_MODULE.MD Phase 10).
 * Chain extension is serialized under a row lock so concurrent writes cannot fork the chain;
 * the hash covers every persisted field (canonical JSON) so any later tampering is detectable.
 */
class FinanceAuditLogger
{
    public function log(Model $auditable, string $eventType, ?User $actor = null, array $opts = []): FinanceAuditLog
    {
        return DB::transaction(function () use ($auditable, $eventType, $actor, $opts) {
            // Lock the current chain head so a concurrent writer cannot read the same
            // previous_hash and fork the chain.
            $head = FinanceAuditLog::query()->lockForUpdate()->orderByDesc('id')->first();
            $previousHash = $head?->current_hash;

            $row = [
                'auditable_type' => get_class($auditable),
                'auditable_id' => $auditable->getKey(),
                'event_type' => $eventType,
                'actor_id' => $actor?->id,
                'actor_role' => $actor?->getRoleNames()->first(),
                'before_snapshot' => $opts['before'] ?? null,
                'after_snapshot' => $opts['after'] ?? null,
                'reason' => $opts['reason'] ?? null,
                'ip_address' => $this->safe(fn () => request()?->ip()),
                'user_agent' => $this->safe(fn () => request()?->userAgent()),
                'created_at' => now(),
            ];

            return FinanceAuditLog::create($row + [
                'previous_hash' => $previousHash,
                'current_hash' => $this->chainHash($row, $previousHash),
            ]);
        });
    }

    /** Recompute the chain hash from a stored row — used to verify the chain has not been tampered with. */
    public function recompute(FinanceAuditLog $log): string
    {
        return $this->chainHash([
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'event_type' => $log->event_type,
            'actor_id' => $log->actor_id,
            'actor_role' => $log->actor_role,
            'before_snapshot' => $log->before_snapshot,
            'after_snapshot' => $log->after_snapshot,
            'reason' => $log->reason,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'created_at' => $log->created_at,
        ], $log->previous_hash);
    }

    /** Canonical (sorted-key) hash over every persisted field + the previous hash. */
    private function chainHash(array $row, ?string $previousHash): string
    {
        $createdAt = $row['created_at'] instanceof \DateTimeInterface
            ? $row['created_at']->toIso8601String()
            : (string) $row['created_at'];

        $payload = [
            'auditable_type' => $row['auditable_type'],
            'auditable_id' => $row['auditable_id'],
            'event_type' => $row['event_type'],
            'actor_id' => $row['actor_id'],
            'actor_role' => $row['actor_role'],
            'before_snapshot' => $row['before_snapshot'],
            'after_snapshot' => $row['after_snapshot'],
            'reason' => $row['reason'],
            'ip_address' => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'created_at' => $createdAt,
            'previous_hash' => $previousHash,
        ];
        ksort($payload);

        return hash('sha256', json_encode($payload));
    }

    private function safe(callable $fn): ?string
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
