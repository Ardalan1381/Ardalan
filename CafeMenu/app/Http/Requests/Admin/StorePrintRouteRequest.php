<?php

namespace App\Http\Requests\Admin;

use App\Models\PrintRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrintRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PrintRoute::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'document_type' => ['required', Rule::in(['receipt', 'kitchen_docket', 'bar_docket', 'invoice'])],
            'printer_id' => ['required', 'exists:printers,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'is_active' => ['boolean'],
        ];
    }
}
