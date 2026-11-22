<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'uploaded_by_id',
        'disk',
        'storage_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(TicketNote::class, 'note_id');
    }
}