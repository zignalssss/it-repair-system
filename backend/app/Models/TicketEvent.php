<?php

namespace App\Models;

use App\Enums\SupportLevel;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'actor_role',
        'type',
        'audience',
        'from_status',
        'to_status',
        'from_level',
        'to_level',
        'from_assignee_id',
        'to_assignee_id',
        'note_id',
        'metadata',
        'information_request_id',
        'equipment_request_id',
    ];

    protected function casts(): array
    {
        return [
            'actor_role' => UserRole::class,
            'from_status' => TicketStatus::class,
            'to_status' => TicketStatus::class,
            'from_level' => SupportLevel::class,
            'to_level' => SupportLevel::class,
            'metadata' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(TicketNote::class, 'note_id');
    }

    public function informationRequest(): BelongsTo
    {
        return $this->belongsTo(InformationRequest::class,'information_request_id');
    }

    public function equipmentRequest(): BelongsTo
    {
        return $this->belongsTo(EquipmentRequest::class,'equipment_request_id',);
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereIn('audience', $user->role->readableAudiences())
            ->whereHas('ticket', function (Builder $tickets) use ($user) {
                $tickets->visibleTo($user);
            });
    }
}