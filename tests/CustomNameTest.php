<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

it('registers the mailer under the customized name', function () {
    expect(config('mail.mailers.mio-mailer'))->toBe([
        'transport' => 'mio-mailer',
        'host' => 'https://mailer.test/api/send',
        'api_key' => 'test-api-key',
        'sync' => false,
    ]);
});

it('sends the email through the mailer registered with the customized name', function () {
    Http::fake();

    Mail::raw('Messaggio', function ($message) {
        $message->to('destinatario@example.com')->subject('Nome personalizzato');
    });

    Http::assertSent(function (Request $request) {
        return $request->url() === 'https://mailer.test/api/send'
            && $request->header('X-API-KEY') === ['test-api-key']
            && $request['subject'] === 'Nome personalizzato';
    });
});
