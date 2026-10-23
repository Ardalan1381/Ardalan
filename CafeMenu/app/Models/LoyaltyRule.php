<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyRule extends Model
{
    protected $fillable = [
        'name',
        'type',
        'min_spend_amount',
        'points_earned',
        'buy_quantity',
        'get_bonus_points',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'min_spend_amount' => 'integer',
            'points_earned' => 'integer',
            'buy_quantity' => 'integer',
            'get_bonus_points' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LoyaltyLedgerEntry::class, 'loyalty_rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
