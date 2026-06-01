<?php

namespace App\Services\Hris;

use App\Models\Hris\FinanceAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes hash-chained, append-only finance audit entries (EXPENSE_MODULE.MD Phase 10).
 */
class FinanceAuditLogger
{
    public function log(Model $auditable, string $eventType, ?User $actor = null, array $opts = []): FinanceAuditLog
    {
        $previousHash = FinanceAuditLog::orderByDesc('id')->value('current_hash');
        $createdAt = now();

        $payload = [
            'auditable_type' => get_class($auditable),
            'auditable_id' => $auditable->getKey(),
            'event_type' => $eventType,
            'actor_id' => $actor?->id,
            'before' => $opts['before'] ?? null,
            'after' => $opts['after'] ?? null,
            'reason' => $opts['reason'] ?? null,
            'created_at' => $createdAt->toIso8601String(),
        ];

        $currentHash = hash('sha256', json_encode($payload).($previousHash ?? ''));

        return FinanceAuditLog::create([
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
            'previous_hash' => $previousHash,
            'current_hash' => $currentHash,
            'created_at' => $createdAt,
        ]);
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
