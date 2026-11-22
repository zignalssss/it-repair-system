<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairPartUsed extends Model
{
    protected $table = 'repair_parts_used';

    public $timestamps = false;

    protected $fillable = [
        'equipment_request_item_id',
        'name',
        'quantity',
        'recorded_by_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(RepairTicket::class, 'ticket_id');
    }

    public function equipmentRequestItem(): BelongsTo
    {
        return $this->belongsTo(EquipmentRequestItem::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }
}