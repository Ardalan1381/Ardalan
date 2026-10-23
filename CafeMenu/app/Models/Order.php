<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Order extends Model
{
    /**
     * Statuses that represent an order still in flight (not yet finished,
     * cancelled, or rejected). Shared by reporting and the Kitchen Display
     * queue so both agree on what "active" means.
     *
     * @var list<string>
     */
    public const ACTIVE_STATUSES = ['pending', 'confirmed', 'preparing', 'ready'];

    protected $fillable = [
        'type',
        'table_id',
        'table_session_id',
        'customer_id',
        'waiter_id',
        'source',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'notes',
        'subtotal',
        'discount_total',
        'tax_total',
        'service_charge_total',
        'grand_total',
        'currency',
        'status',
        'status_changed_at',
        'inventory_consumed_at',
        'payment_status',
        'idempotency_key',
        'idempotency_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'service_charge_total' => 'integer',
            'grand_total' => 'integer',
            'status_changed_at' => 'datetime',
            'inventory_consumed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->uuid)) {
                $order->uuid = (string) Str::uuid();
            }
        });

        static::created(function (Order $order) {
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-'.$order->created_at->format('ymd').'-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);
                $order->saveQuietly();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function discountRedemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    public function billSplits(): HasMany
    {
        return $this->hasMany(BillSplit::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function printJobs(): MorphMany
    {
        return $this->morphMany(PrintJob::class, 'printable');
    }

    /**
     * Net of completed real Refund records — a Payment's own status stays
     * 'paid' forever once paid (it is never mutated to simulate a refund;
     * see Refund), so a refund must be subtracted here rather than relying
     * on the payment's status to drop out of the paid sum.
     */
    public function totalPaid(): int
    {
        $grossPaid = $this->payments()->paid()->sum('amount');
        $totalRefunded = Refund::where('order_id', $this->id)->where('status', 'completed')->sum('amount');

        return max(0, $grossPaid - $totalRefunded);
    }

    public function balanceDue(): int
    {
        return max(0, $this->grand_total - $this->totalPaid());
    }

    /**
     * Whether this order has sat in its current (non-terminal) status
     * longer than the business's configured Kitchen Display threshold.
     * Purely a computed display label — the status machine itself has no
     * "delayed" state.
     */
    public function isDelayed(int $thresholdMinutes): bool
    {
        if (! in_array($this->status, self::ACTIVE_STATUSES, true) || ! $this->status_changed_at) {
            return false;
        }

        return $this->status_changed_at->diffInMinutes(now()) > $thresholdMinutes;
    }

    /**
     * Derives payment_status from the order's own payment/refund records:
     * fully covered (net of refunds) -> paid; some but not all of what was
     * paid has been refunded -> partially_refunded; everything paid has
     * been refunded -> refunded; otherwise -> pending (a plain partial
     * payment with no refund activity is still 'pending' — partial payment
     * itself is tracked at the payments level, not as a distinct
     * order-level status).
     *
     * The legacy branch (a Payment row itself flipped to status=refunded,
     * the only refund mechanism before the Refund model existed) is kept
     * as a fallback so historical data keeps reporting correctly.
     */
    public function syncPaymentStatus(): void
    {
        $this->refresh();

        $grossPaid = $this->payments()->paid()->sum('amount');
        $totalRefunded = Refund::where('order_id', $this->id)->where('status', 'completed')->sum('amount');
        $netPaid = max(0, $grossPaid - $totalRefunded);

        if ($this->grand_total > 0 && $netPaid >= $this->grand_total) {
            $this->update(['payment_status' => 'paid']);

            return;
        }

        $hasLegacyFullRefund = $this->payments()->where('status', 'refunded')->exists()
            && ! $this->payments()->whereIn('status', ['paid', 'pending'])->exists();

        if ($netPaid === 0 && ($hasLegacyFullRefund || $totalRefunded > 0)) {
            $this->update(['payment_status' => 'refunded']);

            return;
        }

        if ($totalRefunded > 0 && $netPaid > 0) {
            $this->update(['payment_status' => 'partially_refunded']);

            return;
        }

        $this->update(['payment_status' => 'pending']);
    }
}
