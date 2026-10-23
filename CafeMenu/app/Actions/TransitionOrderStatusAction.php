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
        // The status check and every side effect run against a row locked
        // for the duration of the transaction: two staff members (or a
        // double-clicked button) confirming the same order concurrently
        // would otherwise both pass the check on their own stale copy and
        // each consume stock / award loyalty points. The second request
        // waits here, then sees the already-updated status and is refused.
        [$locked, $fromStatus] = DB::transaction(function () use ($order, $newStatus, $user, $note) {
            /** @var Order $locked */
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            $fromStatus = $locked->status;

            if (! in_array($newStatus, self::TRANSITIONS[$fromStatus] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => "Cannot move an order from \"{$fromStatus}\" to \"{$newStatus}\".",
                ]);
            }

            $locked->update(['status' => $newStatus, 'status_changed_at' => now()]);

            $locked->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $newStatus,
                'changed_by_user_id' => $user->id,
                'note' => $note,
            ]);

            // A stock shortfall here throws, rolling back this entire
            // transition — staff cannot confirm an order the kitchen
            // cannot actually fulfil.
            if ($newStatus === 'confirmed') {
                $this->recipeConsumption->consume($locked);
            }

            if ($newStatus === 'cancelled') {
                $this->recipeConsumption->reverse($locked);
            }

            // 'completed' is terminal and the row is locked, so this runs
            // at most once per order (EarnLoyaltyForOrderAction also refuses
            // to post a second 'earn' entry for the same order and rule).
            if ($newStatus === 'completed') {
                $this->earnLoyalty->execute($locked);
            }

            return [$locked, $fromStatus];
        });

        // Keep the caller's instance in step with what was committed.
        $order->setRawAttributes($locked->getAttributes(), true);
        $order->setRelations([]);

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
