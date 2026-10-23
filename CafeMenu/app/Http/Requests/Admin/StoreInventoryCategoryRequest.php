<?php

namespace App\Http\Requests\Admin;

use App\Models\InventoryCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreInventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', InventoryCategory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:inventory_categories,name'],
            'parent_id' => ['nullable', 'exists:inventory_categories,id'],
        ];
    }
}
