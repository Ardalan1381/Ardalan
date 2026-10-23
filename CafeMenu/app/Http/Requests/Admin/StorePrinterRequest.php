<?php

namespace App\Http\Requests\Admin;

use App\Models\Printer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrinterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Printer::class);
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
        $connectionType = $this->input('connection_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'connection_type' => ['required', Rule::in(['network', 'usb'])],
            'ip_address' => [Rule::requiredIf($connectionType === 'network'), 'nullable', 'ip'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'device_path' => [Rule::requiredIf($connectionType === 'usb'), 'nullable', 'string', 'max:255'],
            'paper_width' => ['required', Rule::in(['58mm', '80mm'])],
            'farsi_codepage_table' => ['nullable', 'integer', 'between:0,255'],
            'is_active' => ['boolean'],
        ];
    }
}
