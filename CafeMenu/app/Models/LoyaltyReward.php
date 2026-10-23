<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyReward extends Model
{
    protected $fillable = [
        'name',
        'points_cost',
        'reward_type',
        'value',
        'free_product_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points_cost' => 'integer',
            'value' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function freeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'free_product_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(LoyaltyRedemption::class, 'loyalty_reward_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
