<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Triggers an on-demand customer receipt print for an order. Gated by
 * viewing the order rather than a dedicated permission — printing a receipt
 * is a routine part of serving an order, not a privileged configuration
 * action (unlike printers.manage, which controls the printer/route setup).
 */
class StorePrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('view', $this->route('order'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'printer_id' => ['required', Rule::exists('printers', 'id')->where('is_active', true)],
        ];
    }
}
