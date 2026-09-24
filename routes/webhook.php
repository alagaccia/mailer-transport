<?php

use AndreaLagaccia\MailerTransport\Http\Controllers\WebhookController;
use AndreaLagaccia\MailerTransport\Http\Middleware\VerifyWebhookSignature;
use AndreaLagaccia\MailerTransport\WebhookSettings;
use Illuminate\Support\Facades\Route;

/*
 * Outcome of the emails processed by the mailer. Server-to-server call: no
 * session, no CSRF, no throttle (the mailer notifies one call per recipient),
 * authenticated by token and/or HMAC signature.
 */
Route::post(WebhookSettings::current()['path'], WebhookController::class)
    ->middleware(VerifyWebhookSignature::class)
    ->name('mailer-transport.webhook');
