<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollWorkflowStep extends Model
{
    use HasFactory;

    const STEP_FINANCE = 'finance';

    protected $fillable = [
        'payroll_workflow_config_id',
        'step_type',
        'assignee_type',
        'assignee_user_id',
        'assignee_role',
        'order',
    ];

    /**
     * Get the workflow configuration that owns this step.
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(PayrollWorkflowConfig::class, 'payroll_workflow_config_id');
    }

    /**
     * Get the user assigned to this step (when assignee_type is 'user').
     */
    public function assigneeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    /**
     * Check if a given user can act on this step.
     */
    public function canBeActedOnBy(User $user): bool
    {
        if ($this->assignee_type === 'user') {
            return $this->assignee_user_id === $user->id;
        }

        return $user->hasRole($this->assignee_role);
    }
}
