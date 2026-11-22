<?php

namespace App\Models;

use App\Enums\SupportLevel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairTicket extends Model
{
    protected $fillable = [
        'title',
        'description',
        'location',
        'asset_label',
    ];

    protected $attributes = [
        'status' => 'new',
        'support_level' => 'junior',
        'priority' => 'normal',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'support_level' => SupportLevel::class,
            'priority' => TicketPriority::class,
            'first_started_at' => 'datetime',
            'first_escalated_at' => 'datetime',
            'resolved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('1 = 0');
            return;
        }

        switch ($user->role) {
            case UserRole::User:
                if ($user->department_id === null) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->where('department_id', $user->department_id);
                return;

            case UserRole::JuniorTechnician:
                $query->where(function (Builder $visible) use ($user) {
                    $visible->where('assignee_id', $user->id)
                        ->orWhere(function (Builder $queue) {
                            $queue->whereNull('assignee_id')
                                ->where(
                                    'support_level',
                                    SupportLevel::Junior->value,
                                )
                                ->whereIn('status', [
                                    TicketStatus::New->value,
                                    TicketStatus::Returned->value,
                                ]);
                        });
                });
                return;

            case UserRole::SeniorTechnician:
                $query->where(function (Builder $visible) use ($user) {
                    $visible->where('assignee_id', $user->id)
                        ->orWhereNotNull('first_escalated_at');
                });
                return;

            case UserRole::ITManager:
                return;
        }
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class, 'ticket_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TicketNote::class, 'ticket_id');
    }
}