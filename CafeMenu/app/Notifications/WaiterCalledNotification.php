<?php

namespace App\Notifications;

use App\Models\WaiterCall;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class WaiterCalledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WaiterCall $waiterCall) {}

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
            'waiter_call_id' => $this->waiterCall->id,
            'table' => $this->waiterCall->table->name,
            'message' => "Table {$this->waiterCall->table->name} is calling a waiter",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
