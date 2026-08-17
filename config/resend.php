<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Resend API Key
    |--------------------------------------------------------------------------
    |
    | The Resend API key grants access to Resend's API. The application uses
    | this key to send transactional email through the Resend mail transport.
    |
    */

    'api_key' => env('RESEND_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Resend Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain where the package routes will be accessible from.
    | If the setting is null, Resend will reside under the same domain as your
    | application. Otherwise, this value will be used as the subdomain.
    |
    */

    'domain' => env('RESEND_DOMAIN') ?: null,

    /*
    |--------------------------------------------------------------------------
    | Resend Path
    |--------------------------------------------------------------------------
    |
    | This is the base URI path where the package routes, such as the webhook
    | handler, will be available from. You are free to tweak the path to your
    | preference and application design.
    |
    */

    'path' => env('RESEND_PATH', 'resend'),

    /*
    |--------------------------------------------------------------------------
    | Resend Webhooks
    |--------------------------------------------------------------------------
    |
    | The webhook secret prevents unauthorized requests from reaching the
    | Resend webhook controller. The tolerance limits signed-request drift.
    |
    */

    'webhook' => [
        'secret' => env('RESEND_WEBHOOK_SECRET'),
        'tolerance' => env('RESEND_WEBHOOK_TOLERANCE', 300),
    ],

];
