<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LowStockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public InventoryItem $inventoryItem) {}

    /**
     * @return array<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'inventory_item_id' => $this->inventoryItem->id,
            'inventory_item_name' => $this->inventoryItem->name,
            'current_stock' => (string) $this->inventoryItem->current_stock,
            'min_stock_level' => (string) $this->inventoryItem->min_stock_level,
            'message' => "\"{$this->inventoryItem->name}\" is running low on stock.",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
