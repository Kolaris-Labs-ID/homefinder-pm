<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalStep extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'workflow_id',
        'workflow_snapshot',
        'approvable_type',
        'approvable_id',
        'step_order',
        'approver_id',
        'approver_role',
        'status',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'workflow_snapshot' => 'array',
    ];

    /**
     * Get the approvable model (LeaveRequest, etc.).
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the approver user.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Get the workflow this step belongs to.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'workflow_id');
    }

    /**
     * Get the workflow configuration that should be used for this step.
     * Returns the snapshot if available (for in-progress approvals),
     * otherwise falls back to the current workflow configuration.
     *
     * @return array|null
     */
    public function getWorkflowConfiguration(): ?array
    {
        // Prefer snapshot for in-progress approvals to ensure isolation
        if ($this->workflow_snapshot) {
            return $this->workflow_snapshot;
        }

        // Fallback to current workflow (for legacy data)
        if ($this->workflow) {
            return [
                'workflow_id' => $this->workflow->id,
                'workflow_name' => $this->workflow->name,
                'for_module' => $this->workflow->for_module,
                'steps' => $this->workflow->steps,
                'snapshot_at' => null,
            ];
        }

        return null;
    }

    /**
     * Get the step definitions from the workflow configuration.
     *
     * @return array
     */
    public function getWorkflowSteps(): array
    {
        $config = $this->getWorkflowConfiguration();
        return $config['steps'] ?? [];
    }

    /**
     * Check if this step is using a snapshot (isolated from workflow changes).
     *
     * @return bool
     */
    public function hasWorkflowSnapshot(): bool
    {
        return !empty($this->workflow_snapshot);
    }
}
