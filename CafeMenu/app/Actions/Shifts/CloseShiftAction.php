<?php

namespace App\Actions\Shifts;

use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CloseShiftAction
{
    public function execute(Shift $shift, int $closingCashAmount, ?string $notes = null): Shift
    {
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages([
                'shift' => 'This shift is already closed.',
            ]);
        }

        return DB::transaction(function () use ($shift, $closingCashAmount, $notes) {
            $expected = $shift->runningCashTotal();

            $shift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'expected_cash_amount' => $expected,
                'closing_cash_amount' => $closingCashAmount,
                'cash_difference' => $closingCashAmount - $expected,
                'notes' => $notes,
            ]);

            return $shift->fresh();
        });
    }
}
