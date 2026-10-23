<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PurchaseReturn extends Model
{
    protected $fillable = [
        'purchase_id',
        'supplier_id',
        'reference_number',
        'reason',
        'total_amount',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseReturn $return) {
            if (empty($return->reference_number)) {
                $return->reference_number = 'RET-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
