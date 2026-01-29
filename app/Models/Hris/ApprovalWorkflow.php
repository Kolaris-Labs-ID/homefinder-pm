<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    use HasFactory;

    const MODULE_LEAVE = 'leave';
    const MODULE_REIMBURSEMENT = 'reimbursement';
    const MODULE_PAYROLL_ADJUSTMENT = 'payroll_adjustment';
    const MODULE_TRAINING = 'training';

    protected $fillable = [
        'name',
        'for_module',
        'steps',
        'is_active',
    ];

    protected $casts = [
        'steps' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the approval steps for this workflow.
     */
    public function approvalSteps()
    {
        return $this->hasMany(ApprovalStep::class, 'workflow_id');
    }
}
