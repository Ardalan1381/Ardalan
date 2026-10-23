<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gateway Amount Unit
    |--------------------------------------------------------------------------
    |
    | Iranian gateways are configured to expect amounts in either "toman"
    | or "rial" (1 Toman = 10 Rial). This app stores money as an integer
    | in the business's own currency minor units — for Iranian gateways
    | the business currency must be configured with 0 decimal places
    | (see PaymentGatewayManager::availableForCustomers()), and this
    | setting says which unit that whole-number amount should be sent to
    | the gateway as.
    |
    */

    'default_amount_unit' => env('PAYMENT_GATEWAY_AMOUNT_UNIT', 'toman'),

    'gateways' => [

        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
            'sandbox' => env('ZARINPAL_SANDBOX', false),
        ],

        'zibal' => [
            'merchant' => env('ZIBAL_MERCHANT'),
        ],

        'mellat' => [
            'terminal_id' => env('MELLAT_TERMINAL_ID'),
            'username' => env('MELLAT_USERNAME'),
            'password' => env('MELLAT_PASSWORD'),
        ],

    ],

];
