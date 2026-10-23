<?php

namespace App\Http\Requests\Admin;

use App\Models\StockAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', StockAdjustment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(['damage', 'theft', 'expiry', 'correction', 'other'])],
            'notes' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'lines.*.sign' => ['required', Rule::in(['+', '-'])],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Combines each line's sign and magnitude into the signed quantity_delta
     * string RecordStockAdjustmentAction expects.
     *
     * @return array<int, array{inventory_item_id: int, quantity_delta: string, unit_cost: ?int}>
     */
    public function linesForAction(): array
    {
        return collect($this->validated('lines'))->map(fn (array $line) => [
            'inventory_item_id' => $line['inventory_item_id'],
            'quantity_delta' => ($line['sign'] === '-' ? '-' : '').$line['quantity'],
            'unit_cost' => $line['unit_cost'] ?? null,
        ])->all();
    }
}
