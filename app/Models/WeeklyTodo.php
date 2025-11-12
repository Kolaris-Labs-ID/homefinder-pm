<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyTodo extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'week_start_date',
        'deadline',
        'title',
        'description',
        'status',
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'deadline' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'pending' => 'gray',
            'in_progress' => 'warning',
            'done' => 'success',
            default => 'gray',
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'done' => 'Done',
            default => 'Pending',
        };
    }
}

