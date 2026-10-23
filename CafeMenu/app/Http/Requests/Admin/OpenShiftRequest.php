<?php

namespace App\Http\Requests\Admin;

use App\Models\Shift;
use Illuminate\Foundation\Http\FormRequest;

class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Shift::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opening_cash_amount' => ['required', 'integer', 'min:0'],
        ];
    }
}
