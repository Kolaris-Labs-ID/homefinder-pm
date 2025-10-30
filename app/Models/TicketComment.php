<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComment extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'comment',
    ];

    protected static function booted()
    {
        static::created(function ($comment) {
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
}
