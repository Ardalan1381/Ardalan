<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Loyalty\LoyaltyLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerLoyaltyController extends Controller
{
    public function adjust(Customer $customer, Request $request, LoyaltyLedgerService $ledger): RedirectResponse
    {
        $this->authorize('loyalty.manage');

        $validated = $request->validate([
            'points' => ['required', 'integer', Rule::notIn([0])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $ledger->post(
                $customer,
                'manual_adjustment',
                (int) $validated['points'],
                notes: $validated['notes'] ?? null,
                actor: $request->user(),
            );
        } catch (ValidationException $e) {
            return redirect()->route('admin.customers.edit', $customer)->withErrors($e->errors());
        }

        return redirect()->route('admin.customers.edit', $customer)->with('status', __('admin.flash.loyalty_balance_adjusted'));
    }
}
