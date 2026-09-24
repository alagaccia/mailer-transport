<?php

beforeEach(function (): void {
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
