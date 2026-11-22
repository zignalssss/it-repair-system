<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'note_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $audiences = match ($user->role) {
            UserRole::User => ['requester'],

            UserRole::JuniorTechnician => [
                'requester',
                'junior',
            ],

            UserRole::SeniorTechnician => [
                'requester',
                'junior',
                'senior',
            ],

            UserRole::ITManager => [
                'requester',
                'junior',
                'senior',
                'manager',
            ],
        };

        $query->whereIn('audience', $audiences)
            ->whereHas('ticket', function (Builder $tickets) use ($user) {
                $tickets->visibleTo($user);
            });
    }
}