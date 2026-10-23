<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOptionGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_required' => $this->boolean('is_required'),
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
            'selection_type' => ['required', Rule::in(['single', 'multiple'])],
            'is_required' => ['boolean'],
            'min_select' => ['required', 'integer', 'min:0'],
            'max_select' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('selection_type');
            $min = (int) $this->input('min_select', 0);
            $max = $this->filled('max_select') ? (int) $this->input('max_select') : null;
            $required = $this->boolean('is_required');

            if ($type === 'single' && $max !== null && $max !== 1) {
                $validator->errors()->add('max_select', 'A single-selection group cannot allow more than one selection.');
            }

            if ($type === 'single' && $min > 1) {
                $validator->errors()->add('min_select', 'A single-selection group cannot require more than one selection.');
            }

            if ($max !== null && $max < $min) {
                $validator->errors()->add('max_select', 'The maximum selections cannot be less than the minimum selections.');
            }

            if ($required && $min < 1) {
                $validator->errors()->add('min_select', 'A required group must have at least one minimum selection.');
            }
        });
    }
}
