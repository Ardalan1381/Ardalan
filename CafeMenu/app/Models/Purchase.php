<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'supplier_id',
        'purchase_number',
        'status',
        'purchase_date',
        'expected_date',
        'notes',
        'subtotal',
        'tax_total',
        'total_cost',
        'payment_status',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'integer',
            'tax_total' => 'integer',
            'total_cost' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Purchase $purchase) {
            if (empty($purchase->purchase_number)) {
                $purchase->purchase_number = 'PO-'.$purchase->created_at->format('ymd').'-'.str_pad((string) $purchase->id, 5, '0', STR_PAD_LEFT);
                $purchase->saveQuietly();
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
