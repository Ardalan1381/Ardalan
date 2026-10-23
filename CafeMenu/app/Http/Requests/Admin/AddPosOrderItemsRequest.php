<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AddPosOrderItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('addItems', $this->route('order'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.option_ids' => ['nullable', 'array'],
            'items.*.option_ids.*' => ['integer', 'exists:product_options,id'],
        ];
    }
}
