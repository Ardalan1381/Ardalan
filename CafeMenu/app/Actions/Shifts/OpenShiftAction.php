<?php

namespace App\Actions\Shifts;

use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenShiftAction
{
    public function execute(User $user, int $openingCashAmount): Shift
    {
        return DB::transaction(function () use ($user, $openingCashAmount) {
            $existing = Shift::query()->where('user_id', $user->id)->open()->lockForUpdate()->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'shift' => 'You already have an open shift.',
                ]);
            }

            return Shift::create([
                'user_id' => $user->id,
                'status' => 'open',
                'opened_at' => now(),
                'opening_cash_amount' => $openingCashAmount,
            ]);
        });
    }
}
