<?php

return [
    'gateway' => env('BILLING_GATEWAY', 'paystack'),

    // Platform revenue tiles and charts are reported in this single currency.
    'reporting_currency' => env('BILLING_REPORTING_CURRENCY', 'NGN'),

    // Free trial given to new businesses, on the plan with this code. Skipped if the plan does not exist.
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'starter'),

    // Days after a paid period ends during which the business keeps working before it is expired.
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    // Days before the period ends that the owner gets a renewal reminder email.
    'reminder_days' => (int) env('BILLING_REMINDER_DAYS', 3),

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
        // The signature is the real control. IP allow-listing is optional and only trustworthy
        // when trusted proxies are configured, so it is off by default.
        'enforce_ip_allowlist' => (bool) env('PAYSTACK_ENFORCE_IPS', false),
        'webhook_ips' => ['52.31.139.75', '52.49.173.169', '52.214.14.220'],
    ],
];
