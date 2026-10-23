<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoyaltyRewardRequest extends FormRequest
{
    private const VALUE_TYPES = ['fixed_discount_amount', 'percentage_discount'];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('loyaltyReward'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('reward_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'reward_type' => ['required', Rule::in(['fixed_discount_amount', 'percentage_discount', 'free_product'])],
            'value' => [Rule::requiredIf(in_array($type, self::VALUE_TYPES, true)), 'nullable', 'integer', 'min:0'],
            'free_product_id' => [Rule::requiredIf($type === 'free_product'), 'nullable', 'exists:products,id'],
            'is_active' => ['boolean'],
        ];
    }
}
