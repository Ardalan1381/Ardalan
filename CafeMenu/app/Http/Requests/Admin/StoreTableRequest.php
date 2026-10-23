<?php

namespace App\Http\Requests\Admin;

use App\Models\Table;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Table::class);
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
            'table_section_id' => ['required', 'exists:table_sections,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('tables', 'name')->where('table_section_id', $this->input('table_section_id')),
            ],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'status' => ['required', Rule::in(['available', 'out_of_service'])],
            'is_active' => ['boolean'],
        ];
    }
}
