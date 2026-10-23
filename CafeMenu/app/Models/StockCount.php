<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StockCount extends Model
{
    protected $fillable = [
        'reference_number',
        'status',
        'counted_at',
        'notes',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'counted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockCount $count) {
            if (empty($count->reference_number)) {
                $count->reference_number = 'CNT-'.now()->format('ymd').'-'.strtoupper(Str::random(6));
            }
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
