<?php

namespace App\Models;

use App\Support\Quantity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable = [
        'inventory_category_id',
        'name',
        'sku',
        'base_unit_id',
        'min_stock_level',
        'max_stock_level',
        'low_stock_alerted_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_stock_level' => 'decimal:4',
            'max_stock_level' => 'decimal:4',
            'current_stock' => 'decimal:4',
            'average_cost' => 'integer',
            'low_stock_alerted_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'inventory_category_id');
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryUnit::class, 'base_unit_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Recipe lines (across products/add-ons) that consume this item —
     * added when the Recipe/BOM domain introduces RecipeItem.
     */
    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isLowStock(): bool
    {
        return $this->min_stock_level !== null
            && Quantity::compare((string) $this->current_stock, (string) $this->min_stock_level) <= 0;
    }
}
