<?php

namespace App\Actions;

use App\Actions\Loyalty\EarnLoyaltyForOrderAction;
use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Inventory\RecipeConsumptionService;
use App\Services\Printing\PrintRoutingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransitionOrderStatusAction
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed', 'rejected', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'rejected' => [],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly EarnLoyaltyForOrderAction $earnLoyalty,
        private readonly RecipeConsumptionService $recipeConsumption,
        private readonly PrintRoutingService $printRouting,
    ) {}

    public function execute(Order $order, string $newStatus, User $user, ?string $note = null): Order
    {
        $allowed = self::TRANSITIONS[$order->status] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move an order from \"{$order->status}\" to \"{$newStatus}\".",
            ]);
        }

        $fromStatus = $order->status;

        DB::transaction(function () use ($order, $newStatus, $user, $note, $fromStatus) {
            $order->update(['status' => $newStatus, 'status_changed_at' => now()]);

            $order->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by_user_id' => $user->id,
                'note' => $note,
            ]);

            // A stock shortfall here throws, rolling back this entire
            // transition — staff cannot confirm an order the kitchen
            // cannot actually fulfil.
            if ($newStatus === 'confirmed') {
                $this->recipeConsumption->consume($order);
            }

            if ($newStatus === 'cancelled') {
                $this->recipeConsumption->reverse($order);
            }

            // 'completed' is terminal (no outgoing transitions), so this can
            // never double-fire for the same order.
            if ($newStatus === 'completed') {
                $this->earnLoyalty->execute($order);
            }
        });

        event(new OrderStatusChanged($order, $fromStatus, $newStatus));

        // Fired after commit, same as the event above — a docket printing
        // (or failing to print) must never affect whether the status
        // transition itself succeeds.
        if ($newStatus === 'confirmed') {
            $this->printRouting->queueDocketJobsForOrder($order);
        }

        $action = match ($newStatus) {
            'cancelled' => 'order.cancelled',
            'rejected' => 'order.rejected',
            default => 'order.status_changed',
        };
        $this->auditLogger->log($action, $order, old: ['status' => $fromStatus], new: ['status' => $newStatus, 'note' => $note]);

        return $order;
    }
}
