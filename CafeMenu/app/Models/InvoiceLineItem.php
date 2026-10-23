<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceLineItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'product_name_snapshot',
        'quantity',
        'unit_price_snapshot',
        'discount_amount_snapshot',
        'line_total_snapshot',
        'option_summary_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_snapshot' => 'integer',
            'discount_amount_snapshot' => 'integer',
            'line_total_snapshot' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
