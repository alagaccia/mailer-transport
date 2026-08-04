<?php

/*
 * The `name` value is used both as the mailer name (MAIL_MAILER) and as the
 * transport name. The resulting mailer is injected automatically into
 * `mail.mailers.{name}`; any key already defined there by the application
 * takes precedence over these values.
 */
return [
    'name' => env('CUSTOM_MAILER_NAME', 'custom'),
    'host' => env('CUSTOM_MAILER_HOST'),
    'api_key' => env('CUSTOM_MAILER_KEY'),
    'sync' => (bool) env('CUSTOM_MAILER_SYNC', false),
];
