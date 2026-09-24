<?php

namespace AndreaLagaccia\MailerTransport\Console;

use AndreaLagaccia\MailerTransport\Support\EnvWriter;
use AndreaLagaccia\MailerTransport\WebhookSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

/**
 * Publishes the configuration and writes the mailer credentials and the
 * webhook settings (token, HMAC secret, signature header) to the .env file,
 * asking for what is not given as an option.
 */
class InstallCommand extends Command
{
    protected $signature = 'mailer-transport:install
        {--host= : URL of the mailer send endpoint (CUSTOM_MAILER_HOST)}
        {--key= : API key of this application on the mailer (CUSTOM_MAILER_KEY)}
        {--webhook-url= : public URL of the webhook, when APP_URL is not the one the mailer can reach}
        {--webhook-token= : token the mailer sends back as X-API-KEY / Bearer}
        {--webhook-secret= : HMAC secret used to sign the notifications ("generate" to create one)}
        {--webhook-header= : header carrying the signature (default X-Signature)}
        {--no-webhook : disable the webhook}
        {--default-mailer : set MAIL_MAILER to the transport name}
        {--no-publish : do not publish config/mailer-transport.php}';

    protected $description = 'Publish the configuration and write the mailer and webhook settings to .env';

    public function handle(): int
    {
        if (! $this->option('no-publish')) {
            $this->call('vendor:publish', ['--tag' => 'mailer-transport-config', '--force' => true]);
        }

        $name = (string) config('mailer-transport.name', 'custom');
        $current = WebhookSettings::current();

        $host = $this->answer('host', 'URL del mailer (endpoint di invio)', (string) config('mailer-transport.host'), required: true);
        $key = $this->answer('key', 'Chiave API di questa applicazione sul mailer', (string) config('mailer-transport.api_key'), required: true);

        $values = [
            'CUSTOM_MAILER_HOST' => $host,
            'CUSTOM_MAILER_KEY' => $key,
        ];

        $webhookEnabled = ! $this->option('no-webhook')
            && ($this->hasWebhookOptions() || ! $this->input->isInteractive() || confirm('Abilitare il webhook con cui il mailer comunica l\'esito degli invii?', $current['enabled']));

        $values['CUSTOM_MAILER_WEBHOOK_ENABLED'] = $webhookEnabled ? 'true' : 'false';

        if ($webhookEnabled) {
            $secret = $this->answer('webhook-secret', 'Segreto HMAC con cui il mailer firma le notifiche (vuoto = generane uno)', (string) $current['secret']);

            if ($secret === '' || $secret === 'generate') {
                $secret = Str::random(64);
            }

            $values['CUSTOM_MAILER_WEBHOOK_SECRET'] = $secret;
            $values['CUSTOM_MAILER_WEBHOOK_SIGNATURE_HEADER'] = $this->answer('webhook-header', 'Intestazione che porta la firma', $current['signature_header'], required: true);
            $values['CUSTOM_MAILER_WEBHOOK_TOKEN'] = $this->answer('webhook-token', 'Token di autenticazione aggiuntivo (vuoto = nessuno)', (string) $current['token']);
            $values['CUSTOM_MAILER_WEBHOOK_URL'] = $this->answer('webhook-url', 'URL pubblico del webhook (vuoto = APP_URL + '.$current['path'].')', (string) $current['url']);
        }

        if ($this->option('default-mailer') || ($this->input->isInteractive() && confirm("Impostare MAIL_MAILER={$name}?", config('mail.default') === $name))) {
            $values['MAIL_MAILER'] = $name;
        }

        (new EnvWriter($this->laravel->environmentFilePath()))->set($values);

        $this->components->info('Impostazioni scritte in '.$this->laravel->environmentFilePath());

        $rows = collect($values)->map(fn (?string $value, string $key) => [$key, $this->mask($key, (string) $value)])->values()->all();
        $this->table(['Chiave', 'Valore'], $rows);

        if ($webhookEnabled) {
            $announced = WebhookSettings::normalize([
                'enabled' => true,
                'path' => $current['path'],
                'url' => $values['CUSTOM_MAILER_WEBHOOK_URL'] ?? null,
            ]);

            $this->components->info('Webhook annunciato al mailer in ogni invio: '.(WebhookSettings::url($announced) ?? '(APP_URL non configurato)').' — nessuna impostazione da fare sul suo pannello.');
        }

        return self::SUCCESS;
    }

    protected function answer(string $option, string $question, string $default, bool $required = false): string
    {
        $value = (string) $this->option($option);

        if ($value !== '') {
            return $value;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return text(label: $question, default: $default, required: $required);
    }

    protected function hasWebhookOptions(): bool
    {
        foreach (['webhook-url', 'webhook-token', 'webhook-secret', 'webhook-header'] as $option) {
            if ((string) $this->option($option) !== '') {
                return true;
            }
        }

        return false;
    }

    protected function mask(string $key, string $value): string
    {
        if ($value === '' || ! Str::contains($key, ['KEY', 'SECRET', 'TOKEN'])) {
            return $value;
        }

        return Str::limit($value, 4, '…');
    }
}
