<?php

use AndreaLagaccia\MailerTransport\Events\EmailProcessed;
use AndreaLagaccia\MailerTransport\WebhookSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/**
 * @return array<string, mixed>
 */
function webhookPayload(array $overrides = []): array
{
    return [
        'event' => 'email.processed',
        'uuid' => '9f1c2b7e-5d3a-4a8b-9f2e-6c1d7a4b3e50',
        'recipient' => 'mario@example.com',
        'subject' => 'Conferma',
        'status' => 'sent',
        'success' => true,
        'attempts' => 1,
        'error' => null,
        'sent_at' => '2026-09-24T10:15:00+02:00',
        'created_at' => '2026-09-24T10:14:58+02:00',
        ...$overrides,
    ];
}

/**
 * Posts the notification signing its exact bytes, as the mailer does.
 *
 * @param  array<string, string>  $headers
 */
function postWebhook(array $payload, ?string $secret = 'segreto-di-prova', string $signatureHeader = 'X-Signature', array $headers = []): TestResponse
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

    if ($secret !== null) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $signatureHeader))] = WebhookSettings::sign($body, $secret);
    }

    foreach ($headers as $name => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', '/api/mailer/webhook', [], [], [], $server, $body);
}

beforeEach(function (): void {
    config()->set('mailer-transport.webhook.secret', 'segreto-di-prova');
    config()->set('mailer-transport.webhook.token', null);
    config()->set('mailer-transport.webhook.signature_header', 'X-Signature');
});

it('registers the endpoint at the configured path', function () {
    expect(route('mailer-transport.webhook', absolute: false))->toBe('/api/mailer/webhook');
});

it('rejects a wrong or missing signature', function (?string $secret) {
    postWebhook(webhookPayload(), $secret)->assertUnauthorized();
})->with([
    'missing' => [null],
    'other secret' => ['un-altro-segreto'],
]);

it('keeps the endpoint closed when neither token nor secret is configured', function () {
    config()->set('mailer-transport.webhook.secret', null);

    postWebhook(webhookPayload(), null)->assertUnauthorized();
});

it('reads the signature from the configured header', function () {
    config()->set('mailer-transport.webhook.signature_header', 'X-Hub-Signature-256');

    postWebhook(webhookPayload(), signatureHeader: 'X-Hub-Signature-256')->assertOk();
    postWebhook(webhookPayload())->assertUnauthorized();
});

it('checks the token sent as api key or bearer', function () {
    config()->set('mailer-transport.webhook.token', 'token-di-prova');

    postWebhook(webhookPayload())->assertUnauthorized();
    postWebhook(webhookPayload(), headers: ['X-API-KEY' => 'sbagliato'])->assertUnauthorized();
    postWebhook(webhookPayload(), headers: ['X-API-KEY' => 'token-di-prova'])->assertOk();
    postWebhook(webhookPayload(), headers: ['Authorization' => 'Bearer token-di-prova'])->assertOk();
});

it('accepts the token alone when no secret is configured', function () {
    config()->set('mailer-transport.webhook.secret', null);
    config()->set('mailer-transport.webhook.token', 'token-di-prova');

    postWebhook(webhookPayload(), null, headers: ['X-API-KEY' => 'token-di-prova'])->assertOk();
});

it('dispatches EmailProcessed with the outcome', function () {
    Event::fake([EmailProcessed::class]);

    postWebhook(webhookPayload())->assertOk();

    Event::assertDispatched(EmailProcessed::class, function (EmailProcessed $event) {
        return $event->uuid === '9f1c2b7e-5d3a-4a8b-9f2e-6c1d7a4b3e50'
            && $event->isSent()
            && $event->error === null
            && $event->sentAt?->toIso8601String() === '2026-09-24T10:15:00+02:00'
            && $event->attempts === 1
            && $event->recipient === 'mario@example.com'
            && $event->subject === 'Conferma'
            && $event->payload['created_at'] === '2026-09-24T10:14:58+02:00';
    });
});

it('dispatches a failure with its error', function () {
    Event::fake([EmailProcessed::class]);

    postWebhook(webhookPayload(['status' => 'failed', 'success' => false, 'error' => 'Connection refused', 'sent_at' => null]))
        ->assertOk();

    Event::assertDispatched(EmailProcessed::class, fn (EmailProcessed $event) => $event->isFailed()
        && $event->error === 'Connection refused'
        && $event->sentAt === null);
});

it('answers the test notification without dispatching anything', function () {
    Event::fake([EmailProcessed::class]);

    postWebhook(webhookPayload(['event' => 'webhook.test', 'uuid' => null]))->assertOk();

    Event::assertNotDispatched(EmailProcessed::class);
});

it('rejects an unknown status', function () {
    postWebhook(webhookPayload(['status' => 'bounced']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('announces its url and credentials in the payload', function () {
    config()->set('app.url', 'https://gestionale.test');
    config()->set('mailer-transport.webhook.token', 'token-di-prova');

    expect(WebhookSettings::payload(WebhookSettings::current()))->toBe([
        'webhook' => 'https://gestionale.test/api/mailer/webhook',
        'webhook_token' => 'token-di-prova',
        'webhook_secret' => 'segreto-di-prova',
        'webhook_signature_header' => 'X-Signature',
    ]);
});

it('prefers the configured public url', function () {
    config()->set('mailer-transport.webhook.url', 'https://pubblico.test/hooks/mailer');

    expect(WebhookSettings::payload(WebhookSettings::current())['webhook'])->toBe('https://pubblico.test/hooks/mailer');
});

it('announces nothing when disabled', function () {
    config()->set('mailer-transport.webhook.enabled', false);

    expect(WebhookSettings::payload(WebhookSettings::current()))->toBe([]);
});
