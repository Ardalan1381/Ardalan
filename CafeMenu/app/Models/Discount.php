<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'value',
        'buy_quantity',
        'get_quantity',
        'free_item_product_id',
        'applies_to',
        'priority',
        'is_stackable',
        'is_active',
        'starts_at',
        'ends_at',
        'start_time',
        'end_time',
        'days_of_week',
        'min_order_amount',
        'min_quantity',
        'campaign_id',
        'usage_limit',
        'usage_limit_per_customer',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'buy_quantity' => 'integer',
            'get_quantity' => 'integer',
            'priority' => 'integer',
            'is_stackable' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'days_of_week' => 'array',
            'min_order_amount' => 'integer',
            'min_quantity' => 'integer',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'used_count' => 'integer',
        ];
    }

    public function freeItemProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'free_item_product_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'discount_products');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'discount_categories');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'discount_customers');
    }

    public function customerGroups(): BelongsToMany
    {
        return $this->belongsToMany(CustomerGroup::class, 'discount_customer_groups');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
