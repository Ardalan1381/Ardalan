<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillSplitPartItem extends Model
{
    protected $fillable = [
        'bill_split_part_id',
        'order_item_id',
        'quantity_allocated',
    ];

    protected function casts(): array
    {
        return [
            'quantity_allocated' => 'integer',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(BillSplitPart::class, 'bill_split_part_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
