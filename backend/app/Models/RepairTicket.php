<?php

namespace App\Models;

use App\Enums\SupportLevel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Model;
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
}