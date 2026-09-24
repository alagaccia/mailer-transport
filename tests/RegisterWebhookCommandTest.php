<?php

use AndreaLagaccia\MailerTransport\WebhookRegistrar;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();
    config()->set('app.url', 'https://gestionale.test');
    config()->set('mailer-transport.webhook.secret', 'segreto');
});

it('derives the webhook settings endpoint from the send endpoint', function (string $host, ?string $expected) {
    expect(WebhookRegistrar::endpoint($host))->toBe($expected);
})->with([
    ['https://mailer.test/api/send', 'https://mailer.test/api/webhook'],
    ['https://mailer.test/api/send/', 'https://mailer.test/api/webhook'],
    ['https://mailer.test/api/other', null],
    ['', null],
]);

it('saves the configured webhook settings on the mailer', function () {
    Http::fake(['*' => Http::response(['message' => 'Impostazioni webhook salvate'])]);

    $this->artisan('mailer-transport:register-webhook')
        ->expectsOutputToContain('https://gestionale.test/api/mailer/webhook')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === 'https://mailer.test/api/webhook'
        && $request->header('X-API-KEY') === ['test-api-key']
        && $request['url'] === 'https://gestionale.test/api/mailer/webhook'
        && $request['secret'] === 'segreto'
        && $request['signature_header'] === 'X-Signature');
});

it('clears the settings on request', function () {
    Http::fake(['*' => Http::response(['message' => 'Impostazioni webhook salvate'])]);

    $this->artisan('mailer-transport:register-webhook', ['--remove' => true])->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT' && $request['url'] === null);
});

it('fails when the mailer refuses the settings', function () {
    Http::fake(['*' => Http::response(['message' => 'The url field must be a valid URL.'], 422)]);

    $this->artisan('mailer-transport:register-webhook')
        ->expectsOutputToContain('Il mailer ha risposto 422: The url field must be a valid URL.')
        ->assertFailed();
});
