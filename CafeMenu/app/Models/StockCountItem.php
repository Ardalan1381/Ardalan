<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id',
        'inventory_item_id',
        'expected_quantity',
        'counted_quantity',
        'variance',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'decimal:4',
            'counted_quantity' => 'decimal:4',
            'variance' => 'decimal:4',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
