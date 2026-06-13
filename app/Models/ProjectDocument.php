<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    use HasFactory;

    public const CATEGORIES = [
        'contract', 'sow', 'invoice', 'report', 'brief',
        'design', 'credential', 'legal', 'meeting_note', 'other',
    ];

    public const CATEGORY_LABELS = [
        'contract' => 'Contract',
        'sow' => 'SOW',
        'invoice' => 'Invoice',
        'report' => 'Report',
        'brief' => 'Brief',
        'design' => 'Design',
        'credential' => 'Credential',
        'legal' => 'Legal',
        'meeting_note' => 'Meeting Note',
        'other' => 'Other',
    ];

    protected $fillable = [
        'project_id', 'uploaded_by', 'disk', 'file_path', 'original_filename',
        'mime_type', 'size_bytes', 'file_hash', 'category', 'description',
        'uploaded_at', 'archived_at', 'archived_by', 'archive_reason',
    ];

    // Internal storage details never leave the server, even if a relation is accidentally serialized.
    protected $hidden = ['file_path', 'file_hash', 'disk'];

    protected $casts = [
        'size_bytes' => 'integer',
        'uploaded_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->whereNotNull('archived_at');
    }

    public function getIsArchivedAttribute(): bool
    {
        return ! is_null($this->archived_at);
    }

    public function getCategoryLabelAttribute(): string
    {
        return self::CATEGORY_LABELS[$this->category] ?? ucfirst((string) $this->category);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = (int) $this->size_bytes;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
