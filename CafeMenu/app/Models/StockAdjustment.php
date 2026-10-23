<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StockAdjustment extends Model
{
    protected $fillable = [
        'reference_number',
        'reason',
        'notes',
        'created_by_user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (StockAdjustment $adjustment) {
            if (empty($adjustment->reference_number)) {
                $adjustment->reference_number = 'ADJ-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
