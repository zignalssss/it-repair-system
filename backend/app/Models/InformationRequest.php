<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InformationRequest extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'requested_by_id',
        'question_note_id',
        'requested_at',
        'response_note_id',
        'responded_by_id',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'ticket_id');
    }

    public function questionNote(): BelongsTo
    {
        return $this->belongsTo(TicketNote::class, 'question_note_id');
    }

    public function responseNote(): BelongsTo
    {
        return $this->belongsTo(TicketNote::class, 'response_note_id');
    }
}