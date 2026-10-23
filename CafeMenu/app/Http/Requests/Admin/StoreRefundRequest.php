<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('refund', $this->route('payment'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'refund_method' => ['nullable', Rule::in(['original_payment_method', 'cash', 'store_credit'])],
        ];
    }
}
