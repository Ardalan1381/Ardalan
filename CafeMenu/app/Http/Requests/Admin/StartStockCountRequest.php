<?php

namespace App\Http\Requests\Admin;

use App\Models\InventoryItem;
use App\Models\StockCount;
use Illuminate\Foundation\Http\FormRequest;

class StartStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockCount::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('select_all_active')) {
            $this->merge(['inventory_item_ids' => InventoryItem::query()->active()->pluck('id')->all()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inventory_item_ids' => ['required', 'array', 'min:1'],
            'inventory_item_ids.*' => ['integer', 'exists:inventory_items,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
