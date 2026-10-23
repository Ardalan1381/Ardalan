<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyLedgerEntry extends Model
{
    protected $fillable = [
        'customer_id',
        'type',
        'points',
        'resulting_balance',
        'order_id',
        'loyalty_rule_id',
        'loyalty_reward_id',
        'expires_at',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'resulting_balance' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LoyaltyRule::class, 'loyalty_rule_id');
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(LoyaltyReward::class, 'loyalty_reward_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
