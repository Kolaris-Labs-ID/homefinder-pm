<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'title', 
        'message',
        'data',
        'read_at'
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime'
    ];

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    public function markAsRead(): bool
    {
        $this->read_at = now();
        return $this->save();
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'data->ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getTicketAttribute()
    {
        if (isset($this->data['ticket_id'])) {
            return Ticket::with('project')->find($this->data['ticket_id']);
        }
        return null;
    }

    public function getTicketNameAttribute()
    {
        $ticket = $this->getTicketAttribute();
        return $ticket ? $ticket->name : null;
    }

    public function getProjectNameAttribute()
    {
        $ticket = $this->getTicketAttribute();
        return $ticket && $ticket->project ? $ticket->project->name : null;
    }
}
