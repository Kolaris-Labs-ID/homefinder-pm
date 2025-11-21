<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'requires_approval',
        'default_annual_quota',
        'carry_forward_allowed',
        'gender_constraint',
        'is_special',
        'rules',
        'is_active',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'default_annual_quota' => 'integer',
        'carry_forward_allowed' => 'boolean',
        'is_special' => 'boolean',
        'rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}

