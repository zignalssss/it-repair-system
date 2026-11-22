<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketNote extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'author_id',
        'author_role',
        'kind',
        'audience',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'author_role' => UserRole::class,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'ticket_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}