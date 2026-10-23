<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('purchase'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipts' => ['required', 'array', 'min:1'],
            'receipts.*.purchase_item_id' => ['required', 'integer', 'exists:purchase_items,id'],
            'receipts.*.quantity' => ['required', 'numeric', 'min:0'],
        ];
    }
}
