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

        $order = DB::transaction(function () use ($data, $table, $business) {
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
