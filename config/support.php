<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chatbot / API intake
    |--------------------------------------------------------------------------
    |
    | POST /api/tickets with "Authorization: Bearer <token>". Leave empty to
    | turn the endpoint off. Generate one with `php artisan support:token`.
    |
    */

    'api_token' => env('SUPPORT_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Support inbox
    |--------------------------------------------------------------------------
    |
    | `tickets:fetch-mail` (every 5 minutes) reads unread mail from this
    | mailbox over IMAP: new mail becomes a ticket, and replies whose
    | subject carries "[#123]" join that ticket. On Hostinger the host is
    | imap.hostinger.com, port 993, ssl. Replies to merchants go out through
    | the normal MAIL_* settings, with Reply-To set to this address.
    |
    */

    'address' => env('SUPPORT_MAIL_ADDRESS'),

    'imap' => [
        'host' => env('SUPPORT_IMAP_HOST'),
        'port' => (int) env('SUPPORT_IMAP_PORT', 993),
        'encryption' => env('SUPPORT_IMAP_ENCRYPTION', 'ssl'),
        'username' => env('SUPPORT_IMAP_USERNAME', env('SUPPORT_MAIL_ADDRESS')),
        'password' => env('SUPPORT_IMAP_PASSWORD'),
        'folder' => env('SUPPORT_IMAP_FOLDER', 'INBOX'),
    ],

    /*
    |--------------------------------------------------------------------------
    | App setups
    |--------------------------------------------------------------------------
    |
    | After each sync, every store that installed an app in the last N days
    | (and isn't a test store) gets a setup with that app's checklist. The
    | window keeps the first backfill from creating setups for years of
    | old installs.
    |
    */

    'setups_auto_days' => (int) env('SETUPS_AUTO_DAYS', 30),

];
