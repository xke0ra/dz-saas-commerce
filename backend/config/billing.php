<?php

return [
    'grace_period_days' => env('BILLING_GRACE_PERIOD_DAYS', 7),

    'renewal_invoice_days_before_period_end' => env('BILLING_RENEWAL_INVOICE_DAYS_BEFORE_PERIOD_END', 7),

    'renewal_reminder_days' => [7, 3, 1],

    'payment_proofs_disk' => env('BILLING_PAYMENT_PROOFS_DISK', env('FILESYSTEM_DISK', 'local')),

    'payment_proofs_directory' => env('BILLING_PAYMENT_PROOFS_DIRECTORY', 'subscription-payment-proofs'),

    'payment_proofs_max_size_kb' => env('BILLING_PAYMENT_PROOFS_MAX_SIZE_KB', 5120),

    'suspend_tenant_when_subscription_suspended' => true,

    'suspend_stores_when_subscription_suspended' => true,
];
