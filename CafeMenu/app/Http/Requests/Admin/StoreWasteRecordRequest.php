<?php

namespace App\Http\Requests\Admin;

use App\Models\WasteRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWasteRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', WasteRecord::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', Rule::in(['spoilage', 'breakage', 'expired', 'preparation_error', 'other'])],
            'notes' => ['nullable', 'string'],
            'wasted_at' => ['nullable', 'date'],
        ];
    }
}
