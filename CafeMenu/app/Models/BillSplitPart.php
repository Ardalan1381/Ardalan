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

    /**
     * Re-derives amount_paid/status from this part's own payment and refund
     * records, and the parent split's settled/open status from its parts.
     * The only writer of those columns: every payment transition
     * (recorded, confirmed later, failed, refunded) calls this, so the
     * cached totals can never drift from the payments they summarise the
     * way incrementally patched totals could.
     */
    public function recalculate(): void
    {
        $grossPaid = (int) $this->payments()->paid()->sum('amount');
        $refunded = (int) Refund::query()
            ->whereIn('payment_id', $this->payments()->select('id'))
            ->where('status', 'completed')
            ->sum('amount');
        $netPaid = max(0, $grossPaid - $refunded);

        $this->update([
            'amount_paid' => $netPaid,
            'status' => $netPaid >= $this->amount_due ? 'paid' : 'pending',
        ]);

        $billSplit = $this->billSplit;
        $billSplit->update([
            'status' => $billSplit->parts()->where('status', '!=', 'paid')->doesntExist() ? 'settled' : 'open',
        ]);
    }
}
