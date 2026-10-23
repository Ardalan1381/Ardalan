<?php

namespace App\Actions;

use App\Events\OrderPlaced;
use App\Models\BusinessSetting;
use App\Models\Customer;
use App\Models\DiscountRedemption;
use App\Models\LoyaltyRedemption;
use App\Models\LoyaltyReward;
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
 * The staff/POS equivalent of PlaceOrderAction: a waiter builds an order on
 * a customer's behalf rather than the customer self-serving through the
 * public menu. Shares CartPricer with the public checkout flow, so pricing,
 * availability, and discount/loyalty rules are identical — this only
 * differs in who is placing the order and on which table.
 */
class CreateStaffOrderAction
{
    public function __construct(
        private readonly TableSessionService $tableSessionService,
        private readonly CartPricer $cartPricer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, User $waiter): Order
    {
        $business = BusinessSetting::current();
        $type = $data['type'];

        $this->assertOrderTypeAccepted($type, $business);

        $table = null;

        if ($type === 'dine_in') {
            $table = $this->assertTableAvailable($data['table_id'] ?? null);
        }

        $customer = ! empty($data['customer_id'])
            ? Customer::query()->findOrFail($data['customer_id'])
            : null;

        $reward = ! empty($data['loyalty_reward_id'])
            ? LoyaltyReward::query()->active()->findOrFail($data['loyalty_reward_id'])
            : null;

        $order = DB::transaction(function () use ($data, $table, $business, $customer, $reward, $waiter, $type) {
            $tableSession = $table ? $this->tableSessionService->openOrReuse($table, $waiter) : null;

            $priced = $this->cartPricer->price($data['items'], $business, $customer, $reward, $waiter);

            $order = Order::create([
                'type' => $type,
                'table_id' => $table?->id,
                'table_session_id' => $tableSession?->id,
                'customer_id' => $customer?->id,
                'waiter_id' => $waiter->id,
                'source' => 'staff_pos',
                'customer_name' => $customer?->name ?? $data['customer_name'] ?? null,
                'customer_phone' => $customer?->phone ?? $data['customer_phone'] ?? null,
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
                'changed_by_user_id' => $waiter->id,
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

            if ($priced['loyalty_redemption']) {
                $redeemed = $priced['loyalty_redemption'];
                $redeemed['ledger_entry']->update(['order_id' => $order->id]);

                LoyaltyRedemption::create([
                    'customer_id' => $customer->id,
                    'loyalty_reward_id' => $reward->id,
                    'order_id' => $order->id,
                    'order_item_id' => $redeemed['line_index'] !== null
                        ? $createdItemsByLineIndex[$redeemed['line_index']]->id
                        : null,
                    'points_spent' => $redeemed['points_spent'],
                    'monetary_value_granted' => $redeemed['amount'],
                ]);
            }

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
            throw ValidationException::withMessages(['table_id' => 'Select a table for a dine-in order.']);
        }

        $table = Table::query()->active()->find($tableId);

        if (! $table || $table->status !== 'available') {
            throw ValidationException::withMessages(['table_id' => 'This table is not currently available for ordering.']);
        }

        return $table;
    }
}
