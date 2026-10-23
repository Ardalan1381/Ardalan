<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50', 'unique:customers,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'customer_group_id' => ['nullable', 'exists:customer_groups,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.unique' => 'A customer with this phone number already exists.',
        ];
    }
}
