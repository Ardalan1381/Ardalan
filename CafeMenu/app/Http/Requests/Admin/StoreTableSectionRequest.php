<?php

namespace App\Http\Requests\Admin;

use App\Models\TableSection;
use Illuminate\Foundation\Http\FormRequest;

class StoreTableSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TableSection::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:table_sections,name'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }
}
