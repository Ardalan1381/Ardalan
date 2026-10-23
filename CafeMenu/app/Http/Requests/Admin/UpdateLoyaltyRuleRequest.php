<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLoyaltyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('loyaltyRule'));
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['spend_threshold', 'buy_x_get_y_points'])],
            'min_spend_amount' => ['nullable', 'integer', 'min:0'],
            'points_earned' => ['nullable', 'integer', 'min:0'],
            'buy_quantity' => ['nullable', 'integer', 'min:1'],
            'get_bonus_points' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');

            if ($type === 'spend_threshold' && $this->input('points_earned') === null) {
                $validator->errors()->add('points_earned', __('validation.required', ['attribute' => __('admin.loyalty_rules.points_earned')]));
            }

            if ($type === 'buy_x_get_y_points') {
                if ($this->input('buy_quantity') === null) {
                    $validator->errors()->add('buy_quantity', __('validation.required', ['attribute' => __('admin.loyalty_rules.buy_quantity')]));
                }

                if ($this->input('get_bonus_points') === null) {
                    $validator->errors()->add('get_bonus_points', __('validation.required', ['attribute' => __('admin.loyalty_rules.get_bonus_points')]));
                }
            }
        });
    }
}
