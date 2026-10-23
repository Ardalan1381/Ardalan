<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The per-order channel is deliberately public, not private: the customer
 * tracking page has no authenticated session, and the order's UUID (already
 * an unguessable, non-sequential public identifier) is the access control —
 * the same pattern used for table QR tokens elsewhere in this app.
 */
class OrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public Order $order,
        public string $fromStatus,
        public string $toStatus,
    ) {}

    /**
     * @return array<Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders'),
            new Channel("order.{$this->order->uuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status-changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'order_uuid' => $this->order->uuid,
            'order_number' => $this->order->order_number,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
        ];
    }
}
