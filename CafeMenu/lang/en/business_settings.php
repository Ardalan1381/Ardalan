<?php

return [
    'title' => 'Business settings',

    'logo' => [
        'title' => 'Logo',
        'remove' => 'Remove logo',
        'confirm_remove' => 'Remove the logo?',
        'upload' => 'Upload logo',
    ],

    'sections' => [
        'identity' => 'Identity & contact',
        'locale_currency' => 'Locale & currency',
        'display_currencies' => 'Display currencies',
        'tax_service' => 'Tax & service charge',
        'ordering' => 'Ordering',
        'online_payments' => 'Online payments',
        'theme' => 'Theme colors',
        'social_links' => 'Social links',
        'working_hours' => 'Working hours',
    ],

    'online_payments' => [
        'intro' => 'Enter credentials for any gateway you want to accept online payments through. A field left blank keeps its currently saved value — it is never shown back to you once saved.',
        'currency_warning' => 'Online gateways are only offered to customers when the shop currency uses 0 decimal places (Toman/Rial). Current setting: :currency with :decimals decimal place(s).',
        'choose_hint' => 'Once a gateway below has credentials saved, enable it here to offer it to customers at checkout.',
        'enable_label' => 'Offer to customers at checkout',
        'saved_hint' => 'A value is already saved — leave blank to keep it.',
        'zarinpal' => [
            'merchant_id' => 'Merchant ID',
            'sandbox' => 'Sandbox mode (for testing — no real charges)',
        ],
        'zibal' => [
            'merchant' => 'Merchant',
        ],
        'mellat' => [
            'terminal_id' => 'Terminal ID',
            'username' => 'Username',
            'password' => 'Password',
        ],
    ],

    'display_currencies' => [
        'intro' => 'Optionally show a reference price in another currency when the menu is browsed in that language — this never changes what a customer is actually charged, which always stays in your home currency above. Prices for each currency are entered manually per product; there is no automatic conversion.',
        'enable' => 'Show this currency',
        'code' => 'Currency code',
        'symbol' => 'Symbol (optional)',
        'minor_units' => 'Decimal places',
    ],

    'fields' => [
        'business_name' => 'Business name',
        'contact_email' => 'Contact email',
        'contact_phone' => 'Contact phone',
        'address' => 'Address',
        'language' => 'Language',
        'timezone' => 'Timezone',
        'currency_code' => 'Currency code',
        'currency_decimals' => 'Currency decimal places',
        'apply_tax' => 'Apply tax',
        'tax_rate' => 'Tax rate (%)',
        'tax_rate_hint' => 'In basis points: 500 = 5%.',
        'apply_service_charge' => 'Apply service charge',
        'service_charge_rate' => 'Service charge rate (basis points)',
        'primary_color' => 'Primary color',
        'secondary_color' => 'Secondary color',
        'closed' => 'Closed',
        'to' => 'to',
    ],

    'order_types' => [
        'ordering_enabled' => 'Allow customers to place orders',
        'ordering_enabled_hint' => 'When off, customers can only browse the menu — adding to cart and checkout are unavailable to them. Staff ordering from the POS panel is not affected by this setting.',
        'dine_in' => 'Dine-in',
        'takeaway' => 'Takeaway',
        'pickup' => 'Pickup',
        'delivery' => 'Delivery',
        'cash_on_pickup' => 'Accept cash payment for pickup orders',
        'cash_on_pickup_hint' => 'Lets a customer choosing pickup mark that they will pay cash when they collect their order.',
    ],

    'social' => [
        'instagram' => 'Instagram',
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'website' => 'Website',
    ],

    'save' => 'Save settings',
];
