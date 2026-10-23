<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class OrderPlaced implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Order $order) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders')];
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'type' => $this->order->type,
            'table' => $this->order->table?->name,
            'grand_total' => $this->order->grand_total,
        ];
    }
}
