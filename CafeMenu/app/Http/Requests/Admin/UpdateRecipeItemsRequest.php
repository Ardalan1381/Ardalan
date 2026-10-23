<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Backs both the product-level and option-level (add-on) recipe editors —
 * both routes are nested under products/{product}/..., and recipe editing
 * is gated on the same products.manage ability the rest of product
 * management already uses, so a single request class covers both forms.
 */
class UpdateRecipeItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('product'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['nullable', 'array'],
            'lines.*.inventory_item_id' => ['required_with:lines', 'integer', 'exists:inventory_items,id'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.unit_id' => ['required_with:lines', 'integer', 'exists:inventory_units,id'],
        ];
    }

    /**
     * @return array<int, array{inventory_item_id: int, quantity: string, unit_id: int}>
     */
    public function linesForAction(): array
    {
        return collect($this->validated('lines', []))->map(fn (array $line) => [
            'inventory_item_id' => $line['inventory_item_id'],
            'quantity' => (string) $line['quantity'],
            'unit_id' => $line['unit_id'],
        ])->all();
    }
}
