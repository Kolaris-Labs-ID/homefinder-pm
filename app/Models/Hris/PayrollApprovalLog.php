<?php

namespace App\Models\Hris;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayrollApprovalLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'batch_type',
        'batch_id',
        'action',
        'previous_status',
        'new_status',
        'acted_by',
        'notes',
    ];

    /**
     * Get the user who performed this action.
     */
    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    /**
     * Get the batch (PayrunBatch or WeeklyAllowanceBatch) this log belongs to.
     */
    public function batch(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Prevent updates on existing log entries — logs are immutable.
     */
    public function save(array $options = [])
    {
        if ($this->exists) {
            return false;
        }

        return parent::save($options);
    }

    /**
     * Prevent updates via update method.
     */
    public function update(array $attributes = [], array $options = [])
    {
        return false;
    }

    /**
     * Prevent deletion of log entries.
     */
    public function delete()
    {
        return false;
    }
}
