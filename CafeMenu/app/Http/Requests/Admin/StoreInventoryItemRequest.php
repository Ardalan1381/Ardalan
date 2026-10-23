<?php

namespace App\Http\Requests\Admin;

use App\Models\InventoryItem;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', InventoryItem::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:inventory_items,sku'],
            'inventory_category_id' => ['nullable', 'exists:inventory_categories,id'],
            'base_unit_id' => ['required', 'exists:inventory_units,id'],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'max_stock_level' => ['nullable', 'numeric', 'gte:min_stock_level'],
            'is_active' => ['boolean'],
        ];
    }
}
