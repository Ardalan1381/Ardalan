<?php

namespace App\Actions\Shifts;

use App\Models\CashMovement;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Records one traceable cash movement against an open shift. Manual entries
 * (cash_in/cash_out/adjustment, staff-initiated) require a reason; automatic
 * entries (sale/refund, tied to a Payment/Refund via $reference) don't —
 * the reference itself is the explanation.
 */
class RecordCashMovementAction
{
    public function execute(Shift $shift, string $type, int $amount, User $actor, ?string $reason = null, ?Model $reference = null): CashMovement
    {
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages([
                'shift' => 'Cannot record a cash movement against a closed shift.',
            ]);
        }

        if (! $reference && ! $reason) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for a manual cash movement.',
            ]);
        }

        return $shift->cashMovements()->create([
            'type' => $type,
            'amount' => $amount,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reason' => $reason,
            'created_by_user_id' => $actor->id,
        ]);
    }
}
