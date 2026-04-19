<?php

namespace App\Models;

use Database\Factories\ProcessedOrderMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessedOrderMessage extends Model
{
    /** @use HasFactory<ProcessedOrderMessageFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'message_type',
        'order_id',
        'occurred_at',
        'processed_at',
        'payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * Get the order that belongs to the processed message.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
