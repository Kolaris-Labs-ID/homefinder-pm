<?php

namespace App\Models\Hris;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'name',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForDateRange(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
