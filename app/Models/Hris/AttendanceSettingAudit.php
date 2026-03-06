<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSettingAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'field',
        'old_value',
        'new_value',
    ];

    protected $casts = [
        'user_id' => 'integer',
    ];

    /**
     * Get the user who made the change.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
