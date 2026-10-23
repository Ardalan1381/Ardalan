<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('unit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abbreviation' => ['required', 'string', 'max:20'],
            'type' => ['required', Rule::in(['mass', 'volume', 'count'])],
            'base_factor' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
