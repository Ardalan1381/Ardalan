<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /**
     * gateway/gateway_token are deliberately excluded — like Table::qr_token,
     * they must only ever be set by server-side gateway code (explicit
     * property assignment), never via mass assignment from request input.
     */
    protected $fillable = [
        'order_id',
        'bill_split_part_id',
        'method',
        'amount',
        'status',
        'reference',
        'note',
        'recorded_by_user_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function billSplitPart(): BelongsTo
    {
        return $this->belongsTo(BillSplitPart::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function refundedAmount(): int
    {
        return $this->refunds()->where('status', 'completed')->sum('amount');
    }

    public function refundableAmount(): int
    {
        return max(0, $this->amount - $this->refundedAmount());
    }
}
