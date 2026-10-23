<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckLowStockLevels extends Command
{
    protected $signature = 'inventory:check-low-stock';

    protected $description = 'Notify staff about inventory items at or below their minimum stock level';

    public function handle(): void
    {
        $lowStockItems = InventoryItem::query()
            ->active()
            ->whereNotNull('min_stock_level')
            ->whereNull('low_stock_alerted_at')
            ->whereColumn('current_stock', '<=', 'min_stock_level')
            ->get();

        if ($lowStockItems->isEmpty()) {
            return;
        }

        $recipients = User::query()->withPermission('inventory.view')->get();

        foreach ($lowStockItems as $item) {
            Notification::send($recipients, new LowStockNotification($item));
            $item->update(['low_stock_alerted_at' => now()]);
        }

        $this->info("Sent low-stock alerts for {$lowStockItems->count()} item(s).");
    }
}
