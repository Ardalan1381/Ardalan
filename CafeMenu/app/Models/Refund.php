<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    protected $fillable = [
        'payment_id',
        'order_id',
        'amount',
        'reason',
        'status',
        'refund_method',
        'processed_by_user_id',
        'processed_at',
        'resolved_by_user_id',
        'resolved_at',
        'external_reference',
        'resolution_note',
    ];

    /**
     * Refund methods whose money changes hands at the counter, inside this
     * application's own bookkeeping — those complete immediately. Anything
     * else (a card/online reversal, store credit) has to be carried out
     * elsewhere and stays 'pending' until staff confirm it happened.
     */
    public static function settlesImmediately(string $refundMethod, Payment $payment): bool
    {
        return $refundMethod === 'cash'
            || ($refundMethod === 'original_payment_method' && $payment->method === 'cash');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'processed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
