<?php

namespace App\Http\Requests;

use App\Models\BusinessSetting;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['dine_in', 'takeaway', 'pickup', 'delivery'])],
            'customer_name' => ['required_unless:type,dine_in', 'nullable', 'string', 'max:255'],
            'customer_phone' => ['required_unless:type,dine_in', 'nullable', 'string', 'max:50'],
            'delivery_address' => ['required_if:type,delivery', 'nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method' => ['nullable', 'string', Rule::in(['cash'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.option_ids' => ['nullable', 'array'],
            'items.*.option_ids.*' => ['integer', 'exists:product_options,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('payment_method') !== 'cash') {
                return;
            }

            // Cash is only ever collected in person at pickup — never
            // trust the client to have enforced this itself, and never
            // silently drop the field either (that would leave the
            // customer thinking they'd chosen cash when they hadn't).
            if ($this->input('type') !== 'pickup') {
                $validator->errors()->add('payment_method', __('checkout.errors.cash_only_for_pickup'));

                return;
            }

            if (! BusinessSetting::current()->accepts_cash_on_pickup) {
                $validator->errors()->add('payment_method', __('checkout.errors.cash_on_pickup_unavailable'));
            }
        });
    }

    /**
     * Customer-facing messages for the fields a real customer actually
     * fills in — Laravel's default required_if/required_unless phrasing
     * inserts the raw rule parameters verbatim (e.g. "unless type is
     * dine_in") since those values are never translated, which reads as a
     * developer-facing error rather than something an ordering customer
     * can act on.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required_unless' => __('checkout.errors.customer_name_required'),
            'customer_phone.required_unless' => __('checkout.errors.customer_phone_required'),
            'delivery_address.required_if' => __('checkout.errors.delivery_address_required'),
            'items.required' => __('checkout.errors.items_required'),
            'items.min' => __('checkout.errors.items_required'),
        ];
    }
}
