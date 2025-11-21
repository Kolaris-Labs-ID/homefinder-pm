<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'code',
        'level',
    ];

    public function employeePositions(): HasMany
    {
        return $this->hasMany(EmployeePosition::class);
    }
}

