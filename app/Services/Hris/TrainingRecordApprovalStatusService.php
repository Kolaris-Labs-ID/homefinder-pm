<?php

namespace App\Services\Hris;

use App\Models\Hris\ApprovalStep;
use App\Models\Hris\TrainingRecord;

class TrainingRecordApprovalStatusService
{
    public const STATUS_NONE = 'none';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @return array{approval_status: string, rejection_reason: ?string}
     */
    public function forRecord(TrainingRecord $record): array
    {
        $steps = ApprovalStep::query()
            ->where('approvable_type', TrainingRecord::class)
            ->where('approvable_id', $record->id)
            ->orderBy('step_order')
            ->get();

        if ($steps->isEmpty()) {
            return [
                'approval_status' => self::STATUS_NONE,
                'rejection_reason' => null,
            ];
        }

        $rejected = $steps->firstWhere('status', ApprovalStep::STATUS_REJECTED);
        if ($rejected !== null) {
            return [
                'approval_status' => self::STATUS_REJECTED,
                'rejection_reason' => $rejected->notes,
            ];
        }

        if ($steps->contains('status', ApprovalStep::STATUS_PENDING)) {
            return [
                'approval_status' => self::STATUS_PENDING,
                'rejection_reason' => null,
            ];
        }

        return [
            'approval_status' => self::STATUS_APPROVED,
            'rejection_reason' => null,
        ];
    }
}
