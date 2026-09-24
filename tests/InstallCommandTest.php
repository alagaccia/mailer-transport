<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::preventStrayRequests();

    $this->envDir = sys_get_temp_dir().'/mailer-transport-'.uniqid();
    mkdir($this->envDir);
    file_put_contents($this->envDir.'/.env', "APP_NAME=Prova\nCUSTOM_MAILER_HOST=https://vecchio.test/api/send\n");
    $this->app->useEnvironmentPath($this->envDir);
});

afterEach(function (): void {
    @unlink($this->envDir.'/.env');
    @rmdir($this->envDir);
});

it('writes the mailer and webhook settings to the env file', function () {
    config()->set('app.url', 'https://gestionale.test');

    $this->artisan('mailer-transport:install', [
        '--host' => 'https://mailer.test/api/send',
        '--key' => 'chiave-api',
        '--webhook-secret' => 'generate',
        '--webhook-token' => 'token di prova',
        '--default-mailer' => true,
        '--no-publish' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $env = file_get_contents($this->envDir.'/.env');

    expect($env)->toContain("APP_NAME=Prova\n")
        ->toContain("CUSTOM_MAILER_HOST=https://mailer.test/api/send\n")
        ->not->toContain('vecchio.test')
        ->toContain("CUSTOM_MAILER_KEY=chiave-api\n")
        ->toContain("CUSTOM_MAILER_WEBHOOK_ENABLED=true\n")
        ->toMatch('/^CUSTOM_MAILER_WEBHOOK_SECRET=[A-Za-z0-9]{64}$/m')
        ->toContain("CUSTOM_MAILER_WEBHOOK_SIGNATURE_HEADER=X-Signature\n")
        ->toContain("CUSTOM_MAILER_WEBHOOK_TOKEN=\"token di prova\"\n")
        ->toContain("CUSTOM_MAILER_WEBHOOK_URL=https://gestionale.test/api/mailer/webhook\n")
        ->toContain("MAIL_MAILER=custom\n");
});

it('disables the webhook on request', function () {
    $this->artisan('mailer-transport:install', [
        '--host' => 'https://mailer.test/api/send',
        '--key' => 'chiave-api',
        '--no-webhook' => true,
        '--no-publish' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(file_get_contents($this->envDir.'/.env'))
        ->toContain("CUSTOM_MAILER_WEBHOOK_ENABLED=false\n")
        ->not->toContain('CUSTOM_MAILER_WEBHOOK_SECRET');
});

it('writes the public url given as an option', function () {
    $this->artisan('mailer-transport:install', [
        '--host' => 'https://mailer.test/api/send',
        '--key' => 'chiave-api',
        '--webhook-secret' => 'generate',
        '--webhook-url' => 'https://pubblico.test/hooks/mailer',
        '--no-publish' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(file_get_contents($this->envDir.'/.env'))
        ->toContain("CUSTOM_MAILER_WEBHOOK_URL=https://pubblico.test/hooks/mailer\n");
});

it('does not contact the mailer unless asked', function () {
    Http::fake(['*' => Http::response(['message' => 'ok'])]);

    $this->artisan('mailer-transport:install', [
        '--host' => 'https://mailer.test/api/send',
        '--key' => 'chiave-api',
        '--webhook-secret' => 'segreto',
        '--no-publish' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    Http::assertNothingSent();
});

it('saves the webhook settings on the mailer with the values just written', function () {
    Http::fake(['*' => Http::response(['message' => 'ok'])]);

    config()->set('app.url', 'https://gestionale.test');

    $this->artisan('mailer-transport:install', [
        '--host' => 'https://mailer.test/api/send',
        '--key' => 'chiave-api',
        '--webhook-secret' => 'segreto',
        '--webhook-token' => 'token',
        '--webhook-header' => 'X-Hub-Signature-256',
        '--register' => true,
        '--no-publish' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('Impostazioni webhook salvate sul mailer: https://gestionale.test/api/mailer/webhook')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === 'https://mailer.test/api/webhook'
        && $request->header('X-API-KEY') === ['chiave-api']
        && $request['url'] === 'https://gestionale.test/api/mailer/webhook'
        && $request['token'] === 'token'
        && $request['secret'] === 'segreto'
        && $request['signature_header'] === 'X-Hub-Signature-256');
});

it('clears the webhook settings on the mailer when the webhook is disabled', function () {
    Http::fake(['*' => Http::response(['message' => 'ok'])]);

    $this->artisan('mailer-transport:install', [
        '--host' => 'https://mailer.test/api/send',
        '--key' => 'chiave-api',
        '--no-webhook' => true,
        '--register' => true,
        '--no-publish' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    Http::assertSent(fn (Request $request) => $request->method() === 'PUT'
        && $request->url() === 'https://mailer.test/api/webhook'
        && $request['url'] === null);
});

it('keeps the env file and warns when the mailer refuses the settings', function () {
    Http::fake(['*' => Http::response(['error' => 'Unauthorized'], 401)]);

    $this->artisan('mailer-transport:install', [
        '--host' => 'https://mailer.test/api/send',
        '--key' => 'chiave-sbagliata',
        '--webhook-secret' => 'segreto',
        '--register' => true,
        '--no-publish' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('Il mailer ha risposto 401: Unauthorized')
        ->assertSuccessful();

    expect(file_get_contents($this->envDir.'/.env'))->toContain("CUSTOM_MAILER_KEY=chiave-sbagliata\n");
});

it('proposes the values of the env file, not the cached configuration', function () {
    config()->set('mailer-transport.webhook.token', 'token-vecchio-in-cache');
    config()->set('mailer-transport.api_key', 'chiave-vecchia-in-cache');

    file_put_contents($this->envDir.'/.env', implode("\n", [
        'APP_URL=https://dal-file.test',
        'CUSTOM_MAILER_HOST=https://mailer.test/api/send',
        'CUSTOM_MAILER_KEY=chiave-nuova',
        'CUSTOM_MAILER_WEBHOOK_TOKEN=token-nuovo',
        'CUSTOM_MAILER_WEBHOOK_SECRET=segreto-nuovo',
    ])."\n");

    $this->artisan('mailer-transport:install', [
        '--no-publish' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(file_get_contents($this->envDir.'/.env'))
        ->toContain("CUSTOM_MAILER_KEY=chiave-nuova\n")
        ->toContain("CUSTOM_MAILER_WEBHOOK_TOKEN=token-nuovo\n")
        ->toContain("CUSTOM_MAILER_WEBHOOK_SECRET=segreto-nuovo\n")
        ->toContain("CUSTOM_MAILER_WEBHOOK_URL=https://dal-file.test/api/mailer/webhook\n")
        ->not->toContain('in-cache');
});
