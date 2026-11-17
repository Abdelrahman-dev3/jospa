<?php

return [
    'title' => 'Invoices & Payment Links',
    'subtitle' => 'Create a payment request for a fixed amount and send its link to the customer on WhatsApp. Status updates after HyperPay confirms the payment.',
    'disclaimer' => 'This is a simplified payment request, not a ZATCA-compliant tax invoice.',
    'create' => 'Create invoice',
    'create_submit' => 'Create invoice & link',
    'list' => 'Invoices',
    'empty' => 'No invoices yet.',
    'search_placeholder' => 'Invoice number, customer name or mobile',
    'all_statuses' => 'All statuses',
    'filter' => 'Search',

    'fields' => [
        'number' => 'Invoice #',
        'customer_name' => 'Customer name',
        'customer_mobile' => 'Mobile (optional)',
        'description' => 'Service description',
        'amount' => 'Final amount (SAR)',
        'total' => 'Total',
        'status' => 'Status',
        'created_at' => 'Created',
        'paid_at' => 'Paid at',
        'reference' => 'Transaction reference',
        'actions' => 'Actions',
    ],

    'status' => [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],

    'actions' => [
        'view' => 'View',
        'copy' => 'Copy link',
        'copied' => 'Copied',
        'whatsapp' => 'Share on WhatsApp',
        'refresh' => 'Refresh status',
        'cancel' => 'Cancel',
        'cancel_confirm' => 'Cancel this invoice? The customer will no longer be able to pay through the link.',
    ],

    'messages' => [
        'created' => 'Invoice :number created. Copy the link or share it on WhatsApp.',
        'cancelled' => 'Invoice :number cancelled.',
        'refreshed' => 'Invoice :number status: :status',
        'new_link' => 'Payment link for the new invoice',
    ],

    'errors' => [
        'already_paid' => 'This invoice is already paid and cannot be cancelled.',
        'not_payable' => 'This invoice cannot be paid.',
        'invalid_method' => 'Payment method not available.',
        'invalid_mobile' => 'Invalid mobile number.',
        'gateway' => 'The payment gateway is unavailable right now. Please try again shortly.',
    ],

    'public' => [
        'page_title' => 'Payment request',
        'pay_now' => 'Pay now',
        'pay_card' => 'Pay now — Visa / Mastercard',
        'pay_mada' => 'Pay now — mada',
        'paid' => 'This invoice has been paid. Thank you.',
        'paid_now' => 'Payment confirmed. Thank you.',
        'cancelled' => 'This invoice was cancelled. Please contact us if you have questions.',
        'processing' => 'Your payment is being confirmed with the bank. The status will update automatically — please do not pay again.',
        'not_completed' => 'The payment was not completed. You can try again.',
        'secure_note' => 'Payments are processed by the secure HyperPay gateway. We never store your card details.',
    ],

    'notification' => [
        'subject_paid' => 'Invoice :number paid',
        'subject_paid_after_cancel' => 'Cancelled invoice :number was paid',
        'subject_duplicate' => 'Duplicate payment for invoice :number — refund needed',
        'line_paid' => ':name paid SAR :amount for invoice :number.',
        'line_paid_after_cancel' => ':name paid SAR :amount for invoice :number after it was cancelled (the payment page was already open). It was recorded as paid.',
        'line_duplicate' => 'A second payment of SAR :amount was received from :name for invoice :number, which was already paid. A manual refund is required.',
    ],

    'whatsapp_message' => "Hello :name,\nHere is the payment link for invoice :number (SAR :amount):\n:url",
];
