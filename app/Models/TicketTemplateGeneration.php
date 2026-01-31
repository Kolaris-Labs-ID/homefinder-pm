<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTemplateGeneration extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_template_id',
        'ticket_id',
        'target_date',
        'generated_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'generated_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(TicketTemplate::class, 'ticket_template_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
