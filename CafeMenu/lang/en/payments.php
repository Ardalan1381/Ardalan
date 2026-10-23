<?php

return [
    'methods' => [
        'cash' => 'Cash',
        'card' => 'Card',
        'online' => 'Online',
        'pay_later' => 'Pay later',
    ],

    'status' => [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],

    'record_payment' => 'Record payment',
    'balance_due' => 'Balance due',
    'no_payments' => 'No payments recorded yet.',
    'mark_paid' => 'Mark paid',
    'mark_failed' => 'Mark failed',
    'refund' => 'Refund',
    'amount' => 'Amount',
    'reference' => 'Reference',
    'note' => 'Note',
    'reason' => 'Reason',
    'paid_now' => 'Paid now',
    'pending_confirmation' => 'Pending confirmation',
    'confirm_refund' => 'Refund this payment?',
    'refund_method' => 'Refund method',
    'refund_settlement' => [
        'status_label' => 'Status',
        'statuses' => [
            'pending' => 'Awaiting settlement',
            'completed' => 'Completed',
            'failed' => 'Not carried out',
        ],
        'pending_hint' => 'Return the money outside the app (card terminal, gateway dashboard, store credit), then confirm it here.',
        'confirm_settled' => 'Confirm refunded',
        'mark_not_settled' => 'Mark not refunded',
        'external_reference' => 'External reference',
        'confirm_settled_prompt' => 'Confirm this money has actually been returned to the customer?',
        'resolved_by' => 'Resolved by :name',
    ],
    'refund_methods' => [
        'original_payment_method' => 'Original payment method',
        'cash' => 'Cash',
        'store_credit' => 'Store credit',
    ],

    'cash_on_pickup' => [
        'title' => 'Pay with cash',
        'description' => 'Please have :amount ready in cash when you pick up your order.',
    ],

    'pay_online' => [
        'title' => 'Pay online',
        'choose_gateway' => 'Choose a payment method',
        'redirecting' => 'Redirecting you to the payment provider…',
        'nothing_due' => 'There is nothing left to pay on this order.',
        'failed_to_start' => 'Could not start the payment. Please try again.',
    ],

    'gateways' => [
        'zarinpal' => 'ZarinPal',
        'zibal' => 'Zibal',
        'mellat' => 'Bank Mellat',
    ],

    'status_page' => [
        'success' => [
            'title' => 'Payment successful',
            'description' => 'Your payment has been received and confirmed.',
        ],
        'failed' => [
            'title' => 'Payment failed',
            'description' => 'The payment could not be completed. No money was deducted.',
        ],
        'cancelled' => [
            'title' => 'Payment cancelled',
            'description' => 'You cancelled the payment. No money was deducted.',
        ],
        'pending' => [
            'title' => 'Payment pending',
            'description' => 'We are still waiting for confirmation from the payment provider.',
        ],
        'verification_failed' => [
            'title' => 'Verification failed',
            'description' => 'We could not verify this payment. If an amount was deducted, it will be resolved automatically or refunded — please contact us if you need help.',
        ],
        'back_to_order' => 'Back to order',
        'try_again' => 'Try again',
    ],
];
