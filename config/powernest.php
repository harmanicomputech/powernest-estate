<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound integration kill switch
    |--------------------------------------------------------------------------
    | When false, every payment / vending HTTP call is refused before it leaves
    | the application. Keep false everywhere except the production server.
    */
    'integrations_live' => (bool) env('INTEGRATIONS_LIVE', false),

    /*
    | Secret for the web cron endpoint (/cron/{token}) used on hosts without shell access.
    */
    'cron_token' => env('CRON_TOKEN'),

    'display_timezone' => env('APP_DISPLAY_TIMEZONE', 'Africa/Lagos'),

    'currency' => 'NGN',

    /*
    | Defaults applied to new estates. Each estate can override them in its settings.
    | Monetary values are in kobo (minor units).
    */
    'estate_defaults' => [
        /*
    | Retention (months) for operational personal data, pruned daily by model:prune.
    | Financial records, ledger, audit log and incidents are never pruned automatically.
    | Confirm periods with the estate's data protection officer (NDPA 2023).
    */
        'retention' => [
            'visits_months' => (int) env('RETENTION_VISITS_MONTHS', 12),
            'pass_scans_months' => (int) env('RETENTION_PASS_SCANS_MONTHS', 6),
            'visitor_passes_months' => (int) env('RETENTION_VISITOR_PASSES_MONTHS', 12),
            'webhook_events_months' => (int) env('RETENTION_WEBHOOK_EVENTS_MONTHS', 12),
        ],

        'power' => [
            'service_charge' => 25_000,
            'min_amount' => 100_000,
            'max_amount' => 5_000_000,
            'gateways' => ['payaza', 'paystack'],
        ],
        'visitors' => [
            'pass_categories' => ['guest', 'delivery', 'contractor', 'domestic_staff', 'ride_hailing', 'event'],
            'max_pass_days' => 30,
            'require_walk_in_approval' => true,
        ],
        'residents' => [
            'directory_visible' => false,
        ],
        'billing' => [
            'invoice_prefix' => 'INV',
            'reminder_days_before_due' => 3,
        ],
    ],

    /*
    | Retention (months) for operational personal data, pruned daily by model:prune.
    | Financial records, ledger, audit log and incidents are never pruned automatically.
    | Confirm periods with the estate's data protection officer (NDPA 2023).
    */
    'retention' => [
        'visits_months' => (int) env('RETENTION_VISITS_MONTHS', 12),
        'pass_scans_months' => (int) env('RETENTION_PASS_SCANS_MONTHS', 6),
        'visitor_passes_months' => (int) env('RETENTION_VISITOR_PASSES_MONTHS', 12),
        'webhook_events_months' => (int) env('RETENTION_WEBHOOK_EVENTS_MONTHS', 12),
    ],

    'power' => [
        // Pending payments older than this are expired by the reconciliation sweep.
        'payment_expiry_minutes' => 60 * 24,
        // Automatic vend retries only happen when the request provably never reached the vendor.
        'max_auto_vend_attempts' => 3,
    ],

];
