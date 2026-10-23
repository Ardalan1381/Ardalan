<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name_snapshot',
        'product_description_snapshot',
        'unit_price_snapshot',
        'quantity',
        'line_total',
        'discount_total',
        'item_notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price_snapshot' => 'integer',
            'quantity' => 'integer',
            'line_total' => 'integer',
            'discount_total' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(OrderItemOption::class);
    }

    public function discountRedemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }
}
