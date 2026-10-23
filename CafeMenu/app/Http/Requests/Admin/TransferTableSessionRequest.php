<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TransferTableSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tables.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'table_id' => ['required', 'exists:tables,id'],
        ];
    }
}
