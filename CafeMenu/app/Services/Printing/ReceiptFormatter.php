<?php

namespace App\Services\Printing;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\Printer;
use App\Support\LocalizedDate;
use App\Support\Money;
use App\Support\Printing\EscPosDocumentBuilder;

/**
 * Builds the actual ESC/POS byte content for a customer receipt or a
 * kitchen/bar docket. Text-mode throughout — see EscPosDocumentBuilder's
 * doc comment for why (no bitmap/shaping engine available).
 */
class ReceiptFormatter
{
    public function formatReceipt(Order $order, Printer $printer): string
    {
        $width = $printer->columns();
        $business = BusinessSetting::current();
        $builder = $this->newDocument($printer);

        $builder->alignCenter()->boldOn()->text($business->name ?: config('app.name'))->boldOff();

        if ($order->table) {
            $builder->text($order->table->name);
        }

        $builder->alignLeft()
            ->text(LocalizedDate::format($order->created_at))
            ->text($order->order_number)
            ->separator($width);

        foreach ($order->items as $item) {
            $net = $item->line_total - $item->discount_total;
            $builder->twoColumnLine("{$item->quantity}x {$item->product_name_snapshot}", Money::format($net), $width);

            foreach ($item->options as $option) {
                $builder->text('  '.$option->option_name_snapshot);
            }
        }

        $builder->separator($width)
            ->twoColumnLine(__('orders.subtotal'), Money::format($order->subtotal), $width);

        if ($order->discount_total > 0) {
            $builder->twoColumnLine(__('orders.discount'), '-'.Money::format($order->discount_total), $width);
        }

        if ($order->tax_total > 0) {
            $builder->twoColumnLine(__('orders.tax'), Money::format($order->tax_total), $width);
        }

        if ($order->service_charge_total > 0) {
            $builder->twoColumnLine(__('orders.service_charge'), Money::format($order->service_charge_total), $width);
        }

        $builder->boldOn()
            ->twoColumnLine(__('orders.total'), Money::format($order->grand_total), $width)
            ->boldOff()
            ->feed(3)
            ->cut();

        return $builder->toBytes();
    }

    /**
     * A docket has no prices — only what the kitchen/bar needs to prepare
     * the order. $categoryId, when given, prints only the items whose
     * product belongs to that category (direct match, same convention as
     * DiscountEligibilityChecker's category matching elsewhere in this
     * app) — this is how a single order's items get routed to separate
     * kitchen vs. bar printers.
     */
    public function formatDocket(Order $order, Printer $printer, ?int $categoryId, string $title): string
    {
        $width = $printer->columns();
        $builder = $this->newDocument($printer);

        $builder->alignCenter()->boldOn()->doubleHeightOn()->text($title)->doubleHeightOff()->boldOff();

        if ($order->table) {
            $builder->text($order->table->name);
        }

        $builder->alignLeft()
            ->text($order->order_number)
            ->text(LocalizedDate::format($order->created_at))
            ->separator($width);

        $items = $categoryId
            ? $order->items->filter(fn ($item) => $item->product?->category_id === $categoryId)
            : $order->items;

        foreach ($items as $item) {
            $builder->boldOn()->text("{$item->quantity}x {$item->product_name_snapshot}")->boldOff();

            foreach ($item->options as $option) {
                $builder->text('  + '.$option->option_name_snapshot);
            }

            if ($item->item_notes) {
                $builder->text('  '.__('orders.note_prefix', ['note' => $item->item_notes]));
            }
        }

        if ($order->notes) {
            $builder->separator($width)->text(__('orders.notes').': '.$order->notes);
        }

        $builder->feed(3)->cut();

        return $builder->toBytes();
    }

    private function newDocument(Printer $printer): EscPosDocumentBuilder
    {
        $builder = (new EscPosDocumentBuilder)->init();

        if (app()->getLocale() === 'fa' && $printer->farsi_codepage_table) {
            $builder->selectFarsiCodepage($printer->farsi_codepage_table);
        }

        return $builder;
    }
}
