<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('category'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('inventory_categories', 'name')->ignore($this->route('category'))],
            'parent_id' => ['nullable', 'exists:inventory_categories,id', Rule::notIn([$this->route('category')?->id])],
        ];
    }
}
