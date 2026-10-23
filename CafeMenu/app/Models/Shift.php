<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'opened_at',
        'closed_at',
        'opening_cash_amount',
        'expected_cash_amount',
        'closing_cash_amount',
        'cash_difference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash_amount' => 'integer',
            'expected_cash_amount' => 'integer',
            'closing_cash_amount' => 'integer',
            'cash_difference' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function runningCashTotal(): int
    {
        return $this->opening_cash_amount + $this->cashMovements()->sum('amount');
    }
}
