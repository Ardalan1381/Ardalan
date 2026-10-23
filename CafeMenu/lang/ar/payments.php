<?php

return [
    'methods' => [
        'cash' => 'نقدًا',
        'card' => 'بطاقة',
        'online' => 'عبر الإنترنت',
        'pay_later' => 'الدفع لاحقًا',
    ],

    'status' => [
        'pending' => 'قيد الانتظار',
        'paid' => 'مدفوع',
        'failed' => 'فشل',
        'refunded' => 'مسترجع',
    ],

    'record_payment' => 'تسجيل دفعة',
    'balance_due' => 'المبلغ المستحق',
    'no_payments' => 'لم يتم تسجيل أي دفعات بعد.',
    'mark_paid' => 'تحديد كمدفوع',
    'mark_failed' => 'تحديد كفاشل',
    'refund' => 'استرجاع',
    'amount' => 'المبلغ',
    'reference' => 'المرجع',
    'note' => 'ملاحظة',
    'reason' => 'السبب',
    'paid_now' => 'مدفوع الآن',
    'pending_confirmation' => 'بانتظار التأكيد',
    'confirm_refund' => 'استرجاع هذه الدفعة؟',
    'refund_method' => 'طريقة الاسترجاع',
    'refund_methods' => [
        'original_payment_method' => 'طريقة الدفع الأصلية',
        'cash' => 'نقدًا',
        'store_credit' => 'رصيد في المتجر',
    ],

    'cash_on_pickup' => [
        'title' => 'الدفع نقدًا',
        'description' => 'يرجى تجهيز مبلغ :amount نقدًا عند استلام طلبك.',
    ],

    'pay_online' => [
        'title' => 'الدفع عبر الإنترنت',
        'choose_gateway' => 'اختر طريقة دفع',
        'redirecting' => 'جارٍ تحويلك إلى مزود الدفع…',
        'nothing_due' => 'لا يوجد مبلغ متبقٍ للدفع على هذا الطلب.',
        'failed_to_start' => 'تعذّر بدء عملية الدفع. يرجى المحاولة مرة أخرى.',
    ],

    'gateways' => [
        'zarinpal' => 'زرين‌پال',
        'zibal' => 'زيبال',
        'mellat' => 'بنك ملت',
    ],

    'status_page' => [
        'success' => [
            'title' => 'تم الدفع بنجاح',
            'description' => 'تم استلام دفعتك وتأكيدها.',
        ],
        'failed' => [
            'title' => 'فشل الدفع',
            'description' => 'تعذّر إتمام عملية الدفع. لم يُخصم أي مبلغ.',
        ],
        'cancelled' => [
            'title' => 'تم إلغاء الدفع',
            'description' => 'لقد ألغيت عملية الدفع. لم يُخصم أي مبلغ.',
        ],
        'pending' => [
            'title' => 'الدفع قيد الانتظار',
            'description' => 'ما زلنا بانتظار التأكيد من مزود الدفع.',
        ],
        'verification_failed' => [
            'title' => 'فشل التحقق',
            'description' => 'تعذّر التحقق من هذه الدفعة. إذا تم خصم أي مبلغ، فسيُحل الأمر تلقائيًا أو يُسترجع — يرجى التواصل معنا إذا احتجت للمساعدة.',
        ],
        'back_to_order' => 'العودة إلى الطلب',
        'try_again' => 'المحاولة مرة أخرى',
    ],
];
