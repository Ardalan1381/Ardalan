<?php

namespace App\Http\Requests\Admin;

use App\Models\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CustomerGroup::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:customer_groups,name'],
            'description' => ['nullable', 'string'],
        ];
    }
}
