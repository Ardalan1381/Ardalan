<?php

namespace App\Http\Requests\Admin;

use App\Models\BillSplit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Normalizes the fixed "Part 1..4" builder UI (a labeled part per slot, plus
 * a single dropdown per order item picking which slot it belongs to) into
 * the {label, order_item_ids?, amount?} shape CreateBillSplitAction expects.
 * A slot with a blank label is treated as unused and dropped entirely, so
 * staff can leave any of the 4 slots empty without validation errors.
 */
class StoreBillSplitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', BillSplit::class);
    }

    protected function prepareForValidation(): void
    {
        $splitType = $this->input('split_type');
        $rawParts = (array) $this->input('parts', []);
        $itemAssignment = (array) $this->input('item_assignment', []);

        $parts = [];

        foreach ($rawParts as $slot => $data) {
            $label = trim((string) ($data['label'] ?? ''));

            if ($label === '') {
                continue;
            }

            $part = ['label' => $label];

            if ($splitType === 'by_amount') {
                $amount = $data['amount'] ?? null;
                $part['amount'] = $amount !== null && $amount !== '' ? (int) $amount : null;
            }

            if ($splitType === 'by_item') {
                $part['order_item_ids'] = collect($itemAssignment)
                    ->filter(fn ($assignedSlot) => (string) $assignedSlot === (string) $slot)
                    ->keys()
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all();
            }

            $parts[] = $part;
        }

        $this->merge(['parts' => $parts]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('split_type');

        $orderItemIdsRules = $type === 'by_item'
            ? ['required', 'array', 'min:1']
            : ['nullable', 'array'];

        return [
            'split_type' => ['required', Rule::in(['by_item', 'by_amount', 'even'])],
            'parts' => ['required', 'array', 'min:2'],
            'parts.*.label' => ['required', 'string', 'max:255'],
            'parts.*.amount' => [$type === 'by_amount' ? 'required' : 'nullable', 'integer', 'min:0'],
            'parts.*.order_item_ids' => $orderItemIdsRules,
            'parts.*.order_item_ids.*' => ['integer', 'exists:order_items,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'parts.min' => 'A bill split needs at least 2 parts.',
            'parts.*.order_item_ids.required' => 'Assign at least one item to each part.',
            'parts.*.order_item_ids.min' => 'Assign at least one item to each part.',
            'parts.*.amount.required' => 'Enter an amount for each part.',
        ];
    }
}
