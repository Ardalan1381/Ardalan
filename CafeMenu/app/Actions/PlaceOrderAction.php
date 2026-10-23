<?php

namespace App\Actions;

use App\Events\OrderPlaced;
use App\Models\BusinessSetting;
use App\Models\DiscountRedemption;
use App\Models\Order;
use App\Models\Table;
use App\Models\User;
use App\Notifications\NewOrderNotification;
use App\Services\Orders\CartPricer;
use App\Services\TableSessionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Turns a client-submitted cart into a persisted order. The client is never
 * trusted for prices, names, or availability — every value here is
 * re-resolved from the database inside one transaction so the order is
 * created atomically or not at all.
 */
class PlaceOrderAction
{
    public function __construct(
        private readonly TableSessionService $tableSessionService,
        private readonly CartPricer $cartPricer,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated CheckoutRequest data.
     */
    public function execute(array $data, ?int $tableId): Order
    {
        // Idempotency: a checkout carrying a key that already produced an
        // order (double-click, network retry, browser resubmit) gets that
        // same order back instead of a duplicate. Checked before any of the
        // availability rules below, so a retry still succeeds even if, say,
        // ordering was switched off a second after the first attempt.
        $idempotencyKey = $data['idempotency_key'] ?? null;
        $fingerprint = $idempotencyKey ? $this->fingerprint($data, $tableId) : null;

        if ($idempotencyKey && ($existing = $this->replay($idempotencyKey, $fingerprint))) {
            return $existing;
        }

        try {
            return $this->place($data, $tableId, $idempotencyKey, $fingerprint);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent request with the same key won the insert race;
            // this transaction has rolled back (including any discount slot
            // it reserved), so hand back the order the winner created.
            if ($idempotencyKey && ($existing = $this->replay($idempotencyKey, $fingerprint))) {
                return $existing;
            }

            throw $e;
        }
    }

    private function replay(string $idempotencyKey, string $fingerprint): ?Order
    {
        $existing = Order::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing && ! hash_equals((string) $existing->idempotency_fingerprint, $fingerprint)) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This checkout attempt was already used for a different order. Please reload the page and try again.',
            ]);
        }

        return $existing;
    }

    /**
     * Hash of everything that defines the order, so replaying a key is
     * only accepted for the identical request.
     *
     * @param  array<string, mixed>  $data
     */
    private function fingerprint(array $data, ?int $tableId): string
    {
        unset($data['idempotency_key']);
        $data['_table_id'] = $tableId;

        return hash('sha256', json_encode($this->sortRecursive($data)));
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->sortRecursive($item), $value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function place(array $data, ?int $tableId, ?string $idempotencyKey, ?string $fingerprint): Order
    {
        $business = BusinessSetting::current();

        // A separate master switch from accepts_dine_in/takeaway/pickup/
        // delivery below: this is checked only here, in the public/customer
        // checkout path — staff placing an order on a customer's behalf via
        // CreateStaffOrderAction is deliberately unaffected.
        if (! $business->ordering_enabled) {
            throw ValidationException::withMessages(['type' => 'Ordering is currently unavailable — please browse the menu only.']);
        }

        $this->assertOrderTypeAccepted($data['type'], $business);

        $table = null;

        if ($data['type'] === 'dine_in') {
            $table = $this->assertTableAvailable($tableId);
        }

        $order = DB::transaction(function () use ($data, $table, $business, $idempotencyKey, $fingerprint) {
            $tableSession = $table ? $this->tableSessionService->openOrReuse($table) : null;

            $priced = $this->cartPricer->price($data['items'], $business);

            $order = Order::create([
                'type' => $data['type'],
                'table_id' => $table?->id,
                'table_session_id' => $tableSession?->id,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $priced['subtotal'],
                'discount_total' => $priced['discount_total'],
                'tax_total' => $priced['tax_total'],
                'service_charge_total' => $priced['service_charge_total'],
                'grand_total' => $priced['grand_total'],
                'currency' => $business->currency,
                'status' => 'pending',
                'status_changed_at' => now(),
                'payment_status' => 'pending',
                'idempotency_key' => $idempotencyKey,
                'idempotency_fingerprint' => $fingerprint,
            ]);

            $order->statusHistory()->create([
                'from_status' => null,
                'to_status' => 'pending',
            ]);

            $createdItemsByLineIndex = [];

            foreach ($priced['line_items'] as $lineIndex => $line) {
                $orderItem = $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_name_snapshot' => $line['product']->name,
                    'product_description_snapshot' => $line['product']->description,
                    'unit_price_snapshot' => $line['unit_price'],
                    'quantity' => $line['quantity'],
                    'line_total' => $line['line_total'],
                    'discount_total' => $line['discount_total'] ?? 0,
                    'item_notes' => $line['notes'],
                ]);

                $createdItemsByLineIndex[$lineIndex] = $orderItem;

                foreach ($line['options'] as $option) {
                    $orderItem->options()->create([
                        'product_option_id' => $option->id,
                        'option_name_snapshot' => $option->name,
                        'price_delta_snapshot' => $option->price_delta,
                    ]);
                }
            }

            foreach ($priced['discount_redemptions'] as $redemption) {
                DiscountRedemption::create([
                    'discount_id' => $redemption['discount']->id,
                    'order_id' => $order->id,
                    'customer_id' => $order->customer_id,
                    'order_item_id' => $redemption['line_index'] !== null
                        ? $createdItemsByLineIndex[$redemption['line_index']]->id
                        : null,
                    'amount_discounted' => $redemption['amount'],
                    'discount_name_snapshot' => $redemption['discount']->name,
                    'discount_type_snapshot' => $redemption['discount']->type,
                ]);
            }

            // A "pending" (not "paid") record — nobody has handed over cash
            // yet, this only signals to staff that the customer intends to
            // pay in person at pickup. Validated by CheckoutRequest to only
            // ever be 'cash' when type is 'pickup' and the business still
            // accepts it, so no further guard is needed here.
            if (($data['payment_method'] ?? null) === 'cash') {
                $order->payments()->create([
                    'method' => 'cash',
                    'amount' => $priced['grand_total'],
                    'status' => 'pending',
                ]);
            }

            return $order;
        });

        event(new OrderPlaced($order));
        Notification::send(User::query()->withPermission('orders.view')->get(), new NewOrderNotification($order));

        return $order;
    }

    private function assertOrderTypeAccepted(string $type, BusinessSetting $business): void
    {
        $accepted = match ($type) {
            'dine_in' => $business->accepts_dine_in,
            'takeaway' => $business->accepts_takeaway,
            'pickup' => $business->accepts_pickup,
            'delivery' => $business->accepts_delivery,
            default => false,
        };

        if (! $accepted) {
            throw ValidationException::withMessages(['type' => 'This order type is not currently available.']);
        }
    }

    private function assertTableAvailable(?int $tableId): Table
    {
        if (! $tableId) {
            throw ValidationException::withMessages(['type' => 'Please scan the table QR code to order for dine-in.']);
        }

        $table = Table::query()->active()->find($tableId);

        if (! $table || $table->status !== 'available') {
            throw ValidationException::withMessages(['type' => 'This table is not currently available for ordering.']);
        }

        return $table;
    }
}
