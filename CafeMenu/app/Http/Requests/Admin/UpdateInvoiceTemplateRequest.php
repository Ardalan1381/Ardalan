<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoiceTemplate'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'show_logo' => $this->boolean('show_logo'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'paper_size' => ['required', Rule::in(['a4', 'thermal_58mm', 'thermal_80mm'])],
            'show_logo' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }
}
