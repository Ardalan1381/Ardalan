<?php

return [
    'title' => 'Order',
    'note_prefix' => 'Note: :note',
    'subtotal' => 'Subtotal',
    'discount' => 'Discount',
    'tax' => 'Tax',
    'service_charge' => 'Service charge',
    'total' => 'Total',
    'type' => 'Type',
    'table' => 'Table',
    'notes' => 'Notes',
    'back_to_menu' => '← Back to menu',

    'status' => [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'preparing' => 'Preparing',
        'ready' => 'Ready',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'rejected' => 'Rejected',
    ],

    'type_labels' => [
        'dine_in' => 'Dine-in',
        'takeaway' => 'Takeaway',
        'pickup' => 'Pickup',
        'delivery' => 'Delivery',
    ],

    'admin' => [
        'back_to_orders' => '← Back to orders',
        'mark_status' => 'Mark :status',
        'confirm_move_to' => 'Move this order to ":status"?',
        'customer' => 'Customer',
        'delivery_address' => 'Delivery address',
        'by' => 'by :name',
        'ref' => 'ref: :ref',
        'status_history' => 'Status history',
        'created_as' => 'created as :status',
        'transition' => ':from → :to',
    ],
];
