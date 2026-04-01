<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComment extends Model
{
    use HasFactory;
    protected $fillable = [
        'ticket_id',
        'user_id',
        'comment',
    ];

    protected static function booted()
    {
        static::created(function ($comment) {
            ActivityLog::logTicketCommentCreated($comment, auth()->user());
            app(NotificationService::class)->notifyCommentAdded($comment);
        });

        static::updated(function ($comment) {
            if ($comment->wasChanged('comment')) {
                app(NotificationService::class)->notifyCommentUpdated($comment);
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the sanitized comment HTML with proper link protocols
     */
    public function getSanitizedCommentAttribute(): string
    {
        $comment = $this->attributes['comment'] ?? '';
        
        if (empty($comment)) {
            return $comment;
        }

        // Use regex to find and fix href attributes in anchor tags
        // Pattern matches: <a ... href="..." ...> or <a href="...">
        $pattern = '/<a\s+([^>]*?)href=(["\'])([^"\']+)\2([^>]*?)>/i';
        
        return preg_replace_callback($pattern, function ($matches) {
            $beforeHref = $matches[1] ?? '';
            $quote = $matches[2];
            $href = $matches[3];
            $afterHref = $matches[4] ?? '';
            
            // Skip if already has protocol (http:// or https://)
            if (preg_match('/^https?:\/\//i', $href)) {
                return $matches[0];
            }

            // Skip if it's a relative URL (starts with / or #)
            if (preg_match('/^[\/#]/', $href)) {
                return $matches[0];
            }

            // Skip if it's a mailto: link
            if (preg_match('/^mailto:/i', $href)) {
                return $matches[0];
            }

            // If it looks like a domain (contains a dot and doesn't start with / or #)
            // Add https:// protocol
            if (strpos($href, '.') !== false && !preg_match('/^[\/#]/', $href)) {
                $fixedHref = 'https://' . $href;
                
                // Check if target and rel already exist
                $hasTarget = preg_match('/\btarget\s*=/i', $afterHref);
                $hasRel = preg_match('/\brel\s*=/i', $afterHref);
                
                $additionalAttrs = '';
                if (!$hasTarget) {
                    $additionalAttrs .= ' target="_blank"';
                }
                if (!$hasRel) {
                    $additionalAttrs .= ' rel="noopener noreferrer"';
                }
                
                return '<a ' . $beforeHref . 'href=' . $quote . $fixedHref . $quote . $additionalAttrs . $afterHref . '>';
            }

            return $matches[0];
        }, $comment);
    }
}
