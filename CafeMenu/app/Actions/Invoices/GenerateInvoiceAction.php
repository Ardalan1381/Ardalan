<?php

namespace App\Actions\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Generates an invoice for an order — idempotent per order (a second call
 * returns the existing invoice rather than creating a duplicate numbered
 * document). Everything is double-snapshotted from the order's OWN
 * snapshot fields at generation time, so the invoice can never change even
 * if the order/product/customer records are edited afterward.
 *
 * Deliberately manual/on-demand rather than automatic-on-payment: an order
 * paid in a partial or split state has no single sensible "the" invoice
 * moment, so staff decide when to generate one.
 */
class GenerateInvoiceAction
{
    public function execute(Order $order, User $actor, ?InvoiceTemplate $template = null): Invoice
    {
        $existing = Invoice::where('order_id', $order->id)->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $actor, $template) {
            $template ??= InvoiceTemplate::where('is_default', true)->first();

            $invoice = Invoice::create([
                'order_id' => $order->id,
                'issued_at' => now(),
                'customer_name_snapshot' => $order->customer_name,
                'customer_phone_snapshot' => $order->customer_phone,
                'table_name_snapshot' => $order->table?->name,
                'waiter_name_snapshot' => $order->waiter?->name,
                'subtotal' => $order->subtotal,
                'discount_total' => $order->discount_total,
                'tax_total' => $order->tax_total,
                'service_charge_total' => $order->service_charge_total,
                'grand_total' => $order->grand_total,
                'currency' => $order->currency,
                'template_id' => $template?->id,
                'generated_by_user_id' => $actor->id,
            ]);

            foreach ($order->items()->with('options')->get() as $item) {
                $invoice->lineItems()->create([
                    'product_name_snapshot' => $item->product_name_snapshot,
                    'quantity' => $item->quantity,
                    'unit_price_snapshot' => $item->unit_price_snapshot,
                    'discount_amount_snapshot' => $item->discount_total,
                    'line_total_snapshot' => $item->line_total - $item->discount_total,
                    'option_summary_snapshot' => $item->options->pluck('option_name_snapshot')->join(', ') ?: null,
                ]);
            }

            return $invoice->fresh('lineItems');
        });
    }
}
