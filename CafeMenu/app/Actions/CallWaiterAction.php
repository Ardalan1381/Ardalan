<?php

namespace App\Actions;

use App\Models\Table;
use App\Models\User;
use App\Models\WaiterCall;
use App\Notifications\WaiterCalledNotification;
use Illuminate\Support\Facades\Notification;

class CallWaiterAction
{
    /**
     * Reuses an existing pending call for the table instead of stacking up
     * duplicates when a customer taps the button more than once.
     */
    public function execute(Table $table): WaiterCall
    {
        $existing = WaiterCall::query()->where('table_id', $table->id)->pending()->first();

        if ($existing) {
            return $existing;
        }

        $waiterCall = WaiterCall::create([
            'table_id' => $table->id,
            'status' => 'pending',
        ]);

        Notification::send(
            User::query()->withPermission('waiter_calls.view')->get(),
            new WaiterCalledNotification($waiterCall)
        );

        return $waiterCall;
    }
}
