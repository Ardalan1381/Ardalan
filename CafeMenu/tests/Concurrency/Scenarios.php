<?php

namespace Tests\Concurrency;

use App\Actions\PlaceOrderAction;
use App\Actions\ProcessRefundAction;
use App\Actions\RecordPaymentAction;
use App\Actions\TransitionOrderStatusAction;
use App\Actions\UpdatePaymentStatusAction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Table;
use App\Models\User;
use App\Services\TableSessionService;
use InvalidArgumentException;

/**
 * The operations a concurrency worker can run. Each receives plain JSON
 * args (ids, amounts) and returns something JSON-serialisable. Models are
 * loaded fresh inside the worker, exactly as a separate HTTP request would.
 */
final class Scenarios
{
    /**
     * @param  array<string, mixed>  $args
     */
    public static function run(string $scenario, array $args): mixed
    {
        return match ($scenario) {
            'transition' => app(TransitionOrderStatusAction::class)
                ->execute(Order::findOrFail($args['order_id']), $args['status'], User::findOrFail($args['user_id']))
                ->status,
            'record_payment' => app(RecordPaymentAction::class)
                ->execute(Order::findOrFail($args['order_id']), $args['data'], User::findOrFail($args['user_id']))
                ->id,
            'confirm_payment' => app(UpdatePaymentStatusAction::class)
                ->execute(Payment::findOrFail($args['payment_id']), 'paid', User::findOrFail($args['user_id']))
                ->status,
            'refund' => app(ProcessRefundAction::class)
                ->execute(Payment::findOrFail($args['payment_id']), $args['data'], User::findOrFail($args['user_id']))
                ->id,
            'place_order' => app(PlaceOrderAction::class)
                ->execute($args['data'], $args['table_id'] ?? null)
                ->id,
            'open_table_session' => app(TableSessionService::class)
                ->openOrReuse(Table::findOrFail($args['table_id']))
                ->id,
            default => throw new InvalidArgumentException("Unknown scenario [{$scenario}]."),
        };
    }
}
