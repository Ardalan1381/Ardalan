<?php

namespace App\Http\Requests\Admin;

use App\Models\Discount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountRequest extends FormRequest
{
    private const VALUE_TYPES = ['percentage', 'fixed_amount', 'item_discount'];

    public function authorize(): bool
    {
        return $this->user()->can('create', Discount::class);
    }

    protected function prepareForValidation(): void
    {
        $days = collect(range(0, 6))
            ->filter(fn (int $day) => $this->boolean("days_of_week.{$day}"))
            ->values()
            ->all();

        $this->merge([
            'is_stackable' => $this->boolean('is_stackable'),
            'is_active' => $this->boolean('is_active'),
            'days_of_week' => $days,
            'start_time' => $this->normalizeTime($this->input('start_time')),
            'end_time' => $this->normalizeTime($this->input('end_time')),
        ]);
    }

    private function normalizeTime(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        return strlen($value) === 5 ? $value.':00' : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['percentage', 'fixed_amount', 'item_discount', 'half_price', 'buy_x_get_y', 'free_item'])],
            'value' => [Rule::requiredIf(in_array($type, self::VALUE_TYPES, true)), 'nullable', 'integer', 'min:0'],
            'buy_quantity' => [Rule::requiredIf($type === 'buy_x_get_y'), 'nullable', 'integer', 'min:1'],
            'get_quantity' => [Rule::requiredIf($type === 'buy_x_get_y'), 'nullable', 'integer', 'min:1'],
            'free_item_product_id' => [Rule::requiredIf($type === 'free_item'), 'nullable', 'exists:products,id'],
            'applies_to' => ['required', Rule::in(['order', 'specific_products', 'specific_categories'])],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'priority' => ['nullable', 'integer'],
            'is_stackable' => ['boolean'],
            'is_active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'start_time' => ['nullable', 'date_format:H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i:s', 'after:start_time'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:0,6'],
            'min_order_amount' => ['nullable', 'integer', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['integer', 'exists:customers,id'],
            'customer_group_ids' => ['nullable', 'array'],
            'customer_group_ids.*' => ['integer', 'exists:customer_groups,id'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_customer' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
