<?php

namespace AndreaLagaccia\MailerTransport\Console;

use AndreaLagaccia\MailerTransport\WebhookRegistrar;
use AndreaLagaccia\MailerTransport\WebhookSettings;
use Illuminate\Console\Command;

/**
 * Saves (or clears) this application's webhook settings on the mailer, from
 * the current configuration: run it after changing the CUSTOM_MAILER_WEBHOOK_*
 * variables by hand, or when the install command could not reach the mailer.
 */
class RegisterWebhookCommand extends Command
{
    protected $signature = 'mailer-transport:register-webhook
        {--remove : clear the webhook settings on the mailer}';

    protected $description = 'Save this application\'s webhook settings on the mailer (its Settings > Webhook page)';

    public function handle(WebhookRegistrar $registrar): int
    {
        $host = (string) config('mailer-transport.host');
        $apiKey = (string) config('mailer-transport.api_key');
        $settings = WebhookSettings::current();

        $remove = $this->option('remove') || ! $settings['enabled'];

        $result = $remove
            ? $registrar->unregister($host, $apiKey)
            : $registrar->register($host, $apiKey, $settings);

        if ($result !== true) {
            $this->components->error('Impostazioni webhook non '.($remove ? 'azzerate' : 'salvate').' sul mailer.');
            $this->line('  '.$result);

            return self::FAILURE;
        }

        $remove
            ? $this->components->info('Impostazioni webhook azzerate sul mailer.')
            : $this->components->info('Impostazioni webhook salvate sul mailer: '.WebhookSettings::url($settings));

        return self::SUCCESS;
    }
}
