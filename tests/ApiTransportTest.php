<?php

use AndreaLagaccia\MailerTransport\ApiTransport;
use Illuminate\Http\Client\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;

it('merges the mailer into the mail configuration automatically', function () {
    expect(config('mail.mailers.custom'))->toMatchArray([
        'transport' => 'custom',
        'host' => 'https://mailer.test/api/send',
        'api_key' => 'test-api-key',
        'sync' => false,
    ])->and(config('mail.mailers.custom.webhook'))->toBe(config('mailer-transport.webhook'));
});

it('lets the application configuration override the package defaults', function () {
    config()->set('mail.mailers.custom.host', 'https://override.test/api/send');

    expect(config('mail.mailers.custom.host'))->toBe('https://override.test/api/send')
        ->and(config('mail.mailers.custom.transport'))->toBe('custom');
});

it('sends the email through the mailer API', function () {
    Http::fake();

    Mail::raw('Corpo del messaggio', function ($message) {
        $message->to('destinatario@example.com')->subject('Oggetto di prova');
    });

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://mailer.test/api/send'
            && $request->header('X-API-KEY') === ['test-api-key']
            && $request['to'] === ['destinatario@example.com']
            && $request['subject'] === 'Oggetto di prova'
            && $request['body'] === 'Corpo del messaggio'
            && $request['sync'] === false
            && ! array_key_exists('attachments', $request->data());
    });
});

it('sends attachments as base64 encoded content', function () {
    Http::fake();

    Mail::raw('Messaggio con allegato', function ($message) {
        $message->to('destinatario@example.com')
            ->subject('Con allegato')
            ->attachData('contenuto del file', 'documento.txt', ['mime' => 'text/plain']);
    });

    Http::assertSent(function (Request $request) {
        return $request['attachments'] === [
            [
                'filename' => 'documento.txt',
                'content' => base64_encode('contenuto del file'),
                'mime' => 'text/plain',
            ],
        ];
    });
});

it('passes the sync flag from the configuration', function () {
    config()->set('mail.mailers.custom.sync', true);
    Http::fake();

    Mail::raw('Messaggio sincrono', function ($message) {
        $message->to('destinatario@example.com')->subject('Sync');
    });

    Http::assertSent(fn (Request $request) => $request['sync'] === true);
});

it('throws a transport exception when the API call fails', function () {
    Http::fake(fn () => Http::response('errore interno', 500));

    Mail::raw('Messaggio', function ($message) {
        $message->to('destinatario@example.com')->subject('Errore');
    });
})->throws(TransportException::class, 'Errore invio tramite Mailer API');

it('sends the webhook settings along with the message', function () {
    Http::fake();
    config()->set('app.url', 'https://gestionale.test');
    config()->set('mailer-transport.webhook.secret', 'segreto-di-prova');
    config()->set('mail.mailers.custom.webhook', config('mailer-transport.webhook'));

    Mail::raw('Messaggio', function ($message) {
        $message->to('destinatario@example.com')->subject('Con webhook');
    });

    Http::assertSent(fn (Request $request) => $request['webhook'] === 'https://gestionale.test/api/mailer/webhook'
        && $request['webhook_secret'] === 'segreto-di-prova'
        && $request['webhook_signature_header'] === 'X-Signature'
        && ! array_key_exists('webhook_token', $request->data()));
});

it('omits the webhook keys when the webhook is disabled', function () {
    Http::fake();
    config()->set('mail.mailers.custom.webhook', ['enabled' => false]);

    Mail::raw('Messaggio', function ($message) {
        $message->to('destinatario@example.com')->subject('Senza webhook');
    });

    Http::assertSent(fn (Request $request) => ! array_key_exists('webhook', $request->data()));
});

it('gives the message its uuid before it is sent', function () {
    Http::fake();
    $seen = null;

    Event::listen(MessageSending::class, function (MessageSending $event) use (&$seen) {
        $seen = $event->message->getHeaders()->get(ApiTransport::UUID_HEADER)?->getBodyAsString();
    });

    Mail::raw('Messaggio', function ($message) {
        $message->to('destinatario@example.com')->subject('Con uuid');
    });

    expect($seen)->toBeString()->toMatch('/^[0-9a-f-]{36}$/');

    Http::assertSent(fn (Request $request) => $request['uuid'] === $seen);
});

it('preserves an uuid already set on the message', function () {
    Http::fake();

    Mail::raw('Messaggio', function ($message) {
        $message->to('destinatario@example.com')->subject('Con uuid');
        $message->getHeaders()->addTextHeader(ApiTransport::UUID_HEADER, 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
    });

    Http::assertSent(fn (Request $request) => $request['uuid'] === 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
});
