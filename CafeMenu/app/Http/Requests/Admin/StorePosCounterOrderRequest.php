<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePosCounterOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Order::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['takeaway', 'pickup', 'delivery'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'delivery_address' => ['required_if:type,delivery', 'nullable', 'string', 'max:1000'],
            'loyalty_reward_id' => ['nullable', 'integer', 'exists:loyalty_rewards,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.option_ids' => ['nullable', 'array'],
            'items.*.option_ids.*' => ['integer', 'exists:product_options,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('customer_id')) {
                return;
            }

            if (! $this->filled('customer_name') || ! $this->filled('customer_phone')) {
                $validator->errors()->add('customer_name', 'Select an existing customer or enter a name and phone number.');
            }
        });
    }
}
