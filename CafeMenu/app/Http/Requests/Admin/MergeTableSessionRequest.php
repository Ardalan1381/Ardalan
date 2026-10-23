<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MergeTableSessionRequest extends FormRequest
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
            'destination_session_id' => ['required', 'exists:table_sessions,id'],
        ];
    }
}
