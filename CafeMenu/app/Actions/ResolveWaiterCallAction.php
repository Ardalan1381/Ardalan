<?php

namespace App\Actions;

use App\Models\User;
use App\Models\WaiterCall;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class ResolveWaiterCallAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function execute(WaiterCall $waiterCall, User $user): WaiterCall
    {
        if ($waiterCall->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'This waiter call has already been resolved.',
            ]);
        }

        $waiterCall->update([
            'status' => 'resolved',
            'resolved_by_user_id' => $user->id,
            'resolved_at' => now(),
        ]);

        $this->auditLogger->log('waiter_call.resolved', $waiterCall, old: ['status' => 'pending'], new: ['status' => 'resolved']);

        return $waiterCall;
    }
}
