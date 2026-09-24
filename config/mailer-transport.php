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

    /*
     * Webhook with which the mailer reports the outcome of every email.
     *
     * The package registers the endpoint at `path` and sends its full URL,
     * together with the credentials below, in the payload of every message:
     * the mailer then calls back this application without any configuration
     * on its own panel. Incoming notifications are authenticated with the
     * same values (token as X-API-KEY / Bearer, HMAC SHA-256 signature of the
     * raw body in `signature_header`) and turned into the
     * `AndreaLagaccia\MailerTransport\Events\EmailProcessed` event.
     *
     * `url` overrides the public URL announced to the mailer (default:
     * APP_URL + path). Leave both `token` and `secret` empty to keep the
     * endpoint closed.
     */
    'webhook' => [
        'enabled' => (bool) env('CUSTOM_MAILER_WEBHOOK_ENABLED', true),
        'path' => env('CUSTOM_MAILER_WEBHOOK_PATH', 'api/mailer/webhook'),
        'url' => env('CUSTOM_MAILER_WEBHOOK_URL'),
        'token' => env('CUSTOM_MAILER_WEBHOOK_TOKEN'),
        'secret' => env('CUSTOM_MAILER_WEBHOOK_SECRET'),
        'signature_header' => env('CUSTOM_MAILER_WEBHOOK_SIGNATURE_HEADER', 'X-Signature'),
    ],
];
