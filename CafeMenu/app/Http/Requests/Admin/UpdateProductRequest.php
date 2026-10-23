<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('slug') ?: $this->input('name')),
            'is_active' => $this->boolean('is_active'),
            'is_available' => $this->boolean('is_available'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->route('product'))],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'integer', 'min:0'],
            'discount_price' => ['nullable', 'integer', 'min:0'],
            'is_available' => ['boolean'],
            'is_active' => ['boolean'],
            'label' => ['nullable', Rule::in(['new', 'popular', 'special'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'prices' => ['nullable', 'array'],
            'prices.*.price' => ['nullable', 'integer', 'min:0'],
            'prices.*.discount_price' => ['nullable', 'integer', 'min:0'],
            'translations' => ['nullable', 'array'],
            'translations.*.name' => ['nullable', 'string', 'max:255'],
            'translations.*.description' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('discount_price') && $this->integer('discount_price') >= $this->integer('base_price')) {
                $validator->errors()->add('discount_price', 'The discount price must be less than the base price.');
            }

            foreach ($this->input('prices', []) as $locale => $entry) {
                $price = $entry['price'] ?? null;
                $discount = $entry['discount_price'] ?? null;

                if (filled($discount) && filled($price) && (int) $discount >= (int) $price) {
                    $validator->errors()->add("prices.{$locale}.discount_price", 'The discount price must be less than the price.');
                }

                if (filled($discount) && ! filled($price)) {
                    $validator->errors()->add("prices.{$locale}.price", 'A price is required when a discount price is set.');
                }
            }
        });
    }
}
