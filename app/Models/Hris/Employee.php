<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nik',
        'date_of_birth',
        'place_of_birth',
        'gender',
        'marital_status',
        'address',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'bank_name',
        'bank_account_number',
        'bank_account_holder',
        'npwp_number',
        'bpjs_kesehatan_number',
        'bpjs_ketenagakerjaan_number',
        'hire_date',
        'employment_status',
        'employment_end_date',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'hire_date' => 'date',
        'employment_end_date' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }

    public function primaryPosition(): HasOne
    {
        return $this->hasOne(EmployeePosition::class)
            ->ofMany(
                ['start_date' => 'max'],
                fn ($query) => $query->where('is_primary', true)
            );
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EmployeeMovement::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function clientVisits(): HasMany
    {
        return $this->hasMany(ClientVisit::class);
    }

    public function overtimeLogs(): HasMany
    {
        return $this->hasMany(OvertimeLog::class, 'user_id', 'user_id');
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmployeeEmergencyContact::class);
    }

    public function salaryStructures(): HasMany
    {
        return $this->hasMany(SalaryStructure::class);
    }

    public function activeSalaryStructure(): HasOne
    {
        return $this->hasOne(SalaryStructure::class)
            ->ofMany(
                ['effective_date' => 'max'],
                fn ($query) => $query->where('effective_date', '<=', now())
            );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}

