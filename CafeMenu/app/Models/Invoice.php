<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'order_id',
        'invoice_number',
        'issued_at',
        'customer_name_snapshot',
        'customer_phone_snapshot',
        'table_name_snapshot',
        'waiter_name_snapshot',
        'subtotal',
        'discount_total',
        'tax_total',
        'service_charge_total',
        'grand_total',
        'currency',
        'template_id',
        'generated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'service_charge_total' => 'integer',
            'grand_total' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Invoice $invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = 'INV-'.$invoice->created_at->format('ymd').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT);
                $invoice->saveQuietly();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'template_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}
