<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shopify Partner API
    |--------------------------------------------------------------------------
    |
    | Org-level credential from Dev Dashboard → Organization settings → Users
    | → Security → Partner API clients. The client needs "View apps" (app
    | events: installs, uninstalls, charges) and "View financials"
    | (transactions: what was actually earned). The token only ever lives
    | here — it is never stored in the database or shown in the UI.
    |
    | Apps themselves are added in Settings by their Partner app ID: the
    | Partner API has no query that lists an organization's apps.
    |
    */

    'org_id' => env('SHOPIFY_PARTNER_ORG_ID'),

    'api_token' => env('SHOPIFY_PARTNER_API_TOKEN'),

    'api_version' => env('SHOPIFY_PARTNER_API_VERSION', '2026-10'),

    // How far back the first sync (and `partner:sync --full`) reaches.
    'backfill_from' => env('PARTNER_BACKFILL_FROM', '2020-01-01'),

    // Incremental syncs re-read this many days before the last synced
    // point. Everything is upserted on a stable key, so the overlap only
    // re-checks rows — it never double-counts — and it catches events
    // Shopify records late.
    'overlap_days' => (int) env('PARTNER_SYNC_OVERLAP_DAYS', 3),

    // Partner earnings are paid out in USD.
    'currency' => env('PARTNER_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Payout periods
    |--------------------------------------------------------------------------
    |
    | The Partner API returns individual transactions, not the payouts
    | themselves, so payouts are grouped from transactions by date:
    | "semimonthly" (1st–15th, 16th–end of month), "monthly" or "weekly"
    | (Mon–Sun). Treat them as estimates and match them against the payout
    | history in the Partner Dashboard. After changing this, run
    | `php artisan partner:payout-periods` to regroup existing rows.
    |
    */

    'payout_schedule' => env('PARTNER_PAYOUT_SCHEDULE', 'semimonthly'),

];
