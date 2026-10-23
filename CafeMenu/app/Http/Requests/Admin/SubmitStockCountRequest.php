<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SubmitStockCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('count'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'counted' => ['required', 'array', 'min:1'],
            'counted.*.stock_count_item_id' => ['required', 'integer', 'exists:stock_count_items,id'],
            // Nullable — a stock count is filled in incrementally, so a
            // submission may only carry a value for some of its lines.
            // Blank lines are dropped in countedLinesForAction() rather
            // than sent to SubmitStockCountAction.
            'counted.*.counted_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<int, array{stock_count_item_id: int, counted_quantity: string}>
     */
    public function countedLinesForAction(): array
    {
        return collect($this->validated('counted'))
            ->filter(fn (array $line) => $line['counted_quantity'] !== null && $line['counted_quantity'] !== '')
            ->map(fn (array $line) => [
                'stock_count_item_id' => $line['stock_count_item_id'],
                'counted_quantity' => (string) $line['counted_quantity'],
            ])
            ->values()
            ->all();
    }
}
