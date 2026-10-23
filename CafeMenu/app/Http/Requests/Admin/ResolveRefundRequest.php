<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('refund', $this->route('refund')->payment);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'outcome' => ['required', Rule::in(['completed', 'failed'])],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
