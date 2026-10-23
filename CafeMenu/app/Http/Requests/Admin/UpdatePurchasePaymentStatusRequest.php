<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePurchasePaymentStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updatePaymentStatus', $this->route('purchase'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_status' => ['required', Rule::in(['unpaid', 'partially_paid', 'paid'])],
        ];
    }
}
