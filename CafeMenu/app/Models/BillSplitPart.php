<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillSplitPart extends Model
{
    protected $fillable = [
        'bill_split_id',
        'label',
        'amount_due',
        'amount_paid',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_due' => 'integer',
            'amount_paid' => 'integer',
        ];
    }

    public function billSplit(): BelongsTo
    {
        return $this->belongsTo(BillSplit::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillSplitPartItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function balanceDue(): int
    {
        return max(0, $this->amount_due - $this->amount_paid);
    }
}
