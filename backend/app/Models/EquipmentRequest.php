<?php

namespace App\Models;

use App\Enums\EquipmentRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EquipmentRequest extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'requested_by_id',
        'reason',
        'status',
        'requested_at',
        'decided_by_id',
        'decision_reason',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EquipmentRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'estimated_total' => 'decimal:2',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'ticket_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EquipmentRequestItem::class);
    }
}