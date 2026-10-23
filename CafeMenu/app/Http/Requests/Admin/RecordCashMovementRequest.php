<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordCashMovement', $this->route('shift'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // sale/refund are system-only (created automatically by payment
            // recording) — a manual entry may only be one of these three.
            'type' => ['required', Rule::in(['cash_in', 'cash_out', 'adjustment'])],
            // Always entered as a positive magnitude in the UI; direction is
            // derived below via signedAmount(). cash_in is always +, cash_out
            // is always -, and adjustment carries its own sign selector since
            // an adjustment may correct the drawer in either direction.
            'amount' => ['required', 'integer', 'min:1'],
            'sign' => ['required_if:type,adjustment', Rule::in(['+', '-'])],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function signedAmount(): int
    {
        $amount = (int) $this->validated('amount');

        if ($this->validated('type') === 'cash_out') {
            return -$amount;
        }

        if ($this->validated('type') === 'adjustment' && $this->validated('sign') === '-') {
            return -$amount;
        }

        return $amount;
    }
}
