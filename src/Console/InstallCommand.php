<?php

namespace AndreaLagaccia\MailerTransport\Console;

use AndreaLagaccia\MailerTransport\Support\EnvWriter;
use AndreaLagaccia\MailerTransport\WebhookRegistrar;
use AndreaLagaccia\MailerTransport\WebhookSettings;
use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

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
        {--webhook-url= : public URL of the webhook (default APP_URL + path)}
        {--webhook-token= : token the mailer sends back as X-API-KEY / Bearer}
        {--webhook-secret= : HMAC secret used to sign the notifications ("generate" to create one)}
        {--webhook-header= : header carrying the signature (default X-Signature)}
        {--no-webhook : disable the webhook}
        {--register : also save the webhook settings on the mailer (asked when interactive)}
        {--default-mailer : set MAIL_MAILER to the transport name}
        {--no-publish : do not publish config/mailer-transport.php}';

    protected $description = 'Publish the configuration and write the mailer and webhook settings to .env';

    /**
     * @var array<string, string|null>|null
     */
    protected ?array $envFile = null;

    public function handle(WebhookRegistrar $registrar): int
    {
        if (! $this->option('no-publish')) {
            $this->call('vendor:publish', ['--tag' => 'mailer-transport-config', '--force' => true]);
        }

        $name = (string) config('mailer-transport.name', 'custom');
        $current = $this->currentSettings();

        $host = $this->answer('host', 'URL del mailer (endpoint di invio)', $this->fromEnv('CUSTOM_MAILER_HOST', config('mailer-transport.host')), required: true, env: 'CUSTOM_MAILER_HOST');
        $key = $this->answer('key', 'Chiave API di questa applicazione sul mailer', $this->fromEnv('CUSTOM_MAILER_KEY', config('mailer-transport.api_key')), required: true, env: 'CUSTOM_MAILER_KEY');

        $values = [
            'CUSTOM_MAILER_HOST' => $host,
            'CUSTOM_MAILER_KEY' => $key,
        ];

        $webhookEnabled = ! $this->option('no-webhook')
            && ($this->hasWebhookOptions() || ! $this->input->isInteractive() || confirm('Abilitare il webhook con cui il mailer comunica l\'esito degli invii?', $current['enabled']));

        $values['CUSTOM_MAILER_WEBHOOK_ENABLED'] = $webhookEnabled ? 'true' : 'false';

        if ($webhookEnabled) {
            $secret = $this->answer('webhook-secret', 'Segreto HMAC con cui firmare le notifiche (vuoto = generane uno)', (string) $current['secret'], env: 'CUSTOM_MAILER_WEBHOOK_SECRET');

            if ($secret === '' || $secret === 'generate') {
                $secret = Str::random(64);
            }

            $values['CUSTOM_MAILER_WEBHOOK_SECRET'] = $secret;
            $values['CUSTOM_MAILER_WEBHOOK_SIGNATURE_HEADER'] = $this->answer('webhook-header', 'Intestazione che porta la firma', $current['signature_header'], required: true, env: 'CUSTOM_MAILER_WEBHOOK_SIGNATURE_HEADER');
            $values['CUSTOM_MAILER_WEBHOOK_TOKEN'] = $this->answer('webhook-token', 'Token di autenticazione aggiuntivo (vuoto = nessuno)', (string) $current['token'], env: 'CUSTOM_MAILER_WEBHOOK_TOKEN');
            // Si propone l'URL gia' configurato, altrimenti quello che si
            // ricava da APP_URL + path, e lo si scrive comunque nel .env:
            // cosi' si vede a colpo d'occhio dove il mailer richiamera'.
            $values['CUSTOM_MAILER_WEBHOOK_URL'] = $this->answer('webhook-url', 'URL pubblico del webhook', (string) WebhookSettings::url($current), env: 'CUSTOM_MAILER_WEBHOOK_URL');
        }

        if ($this->option('default-mailer') || ($this->input->isInteractive() && confirm("Impostare MAIL_MAILER={$name}?", config('mail.default') === $name))) {
            $values['MAIL_MAILER'] = $name;
        }

        (new EnvWriter($this->laravel->environmentFilePath()))->set($values);

        // Con la configurazione in cache (php artisan optimize) i valori
        // appena scritti non verrebbero letti dall'applicazione: la si
        // rigenera subito, cosi' non resta nulla da fare a mano.
        if ($this->laravel->configurationIsCached()) {
            $this->call('config:cache');
        }

        $this->components->info('Impostazioni scritte in '.$this->laravel->environmentFilePath());

        $rows = collect($values)->map(fn (?string $value, string $key) => [$key, $this->mask($key, (string) $value)])->values()->all();
        $this->table(['Chiave', 'Valore'], $rows);

        if ($this->shouldRegister($host)) {
            $this->registerOnMailer($registrar, $host, $key, $webhookEnabled, $current['path'], $values);
        }

        return self::SUCCESS;
    }

    /**
     * The value of an option, or the answer to a prompt that shows the .env
     * variable it will be written to (the same name the mailer panel shows).
     */
    /**
     * The webhook settings to propose: the ones in the .env file, falling
     * back to the configuration. The .env is read directly because a cached
     * configuration would still hold the values of the last `optimize`.
     *
     * @return array{enabled: bool, path: string, url: string|null, token: string|null, secret: string|null, signature_header: string}
     */
    protected function currentSettings(): array
    {
        $config = (array) config('mailer-transport.webhook', []);

        $appUrl = $this->fromEnv('APP_URL', '');

        if ($appUrl !== '') {
            config()->set('app.url', $appUrl);
        }

        return WebhookSettings::normalize([
            'enabled' => filter_var($this->fromEnv('CUSTOM_MAILER_WEBHOOK_ENABLED', ($config['enabled'] ?? true) ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN),
            'path' => $this->fromEnv('CUSTOM_MAILER_WEBHOOK_PATH', $config['path'] ?? null),
            'url' => $this->fromEnv('CUSTOM_MAILER_WEBHOOK_URL', $config['url'] ?? null),
            'token' => $this->fromEnv('CUSTOM_MAILER_WEBHOOK_TOKEN', $config['token'] ?? null),
            'secret' => $this->fromEnv('CUSTOM_MAILER_WEBHOOK_SECRET', $config['secret'] ?? null),
            'signature_header' => $this->fromEnv('CUSTOM_MAILER_WEBHOOK_SIGNATURE_HEADER', $config['signature_header'] ?? null),
        ]);
    }

    /**
     * A variable as written in the .env file, or the fallback when the file
     * does not define it.
     */
    protected function fromEnv(string $key, mixed $fallback): string
    {
        $this->envFile ??= $this->readEnvFile();

        return array_key_exists($key, $this->envFile)
            ? (string) $this->envFile[$key]
            : (string) $fallback;
    }

    /**
     * @return array<string, string|null>
     */
    protected function readEnvFile(): array
    {
        $path = $this->laravel->environmentFilePath();

        if (! is_file($path)) {
            return [];
        }

        try {
            return Dotenv::parse((string) file_get_contents($path));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * With --register the settings are saved on the mailer without asking;
     * otherwise the question is put only to an interactive user.
     */
    protected function shouldRegister(string $host): bool
    {
        if ($this->option('register')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            return false;
        }

        $endpoint = WebhookRegistrar::endpoint($host) ?? $host;

        return confirm("Salvare questi valori anche nella sezione Webhook del mailer ({$endpoint}, con la chiave API appena indicata)?", true);
    }

    /**
     * Saves the webhook settings on the mailer (or clears them when the
     * webhook is disabled) with the values just written: the configuration
     * in memory is still the old one. A failure does not undo the .env, it
     * only asks to retry.
     *
     * @param  array<string, string|null>  $values
     */
    protected function registerOnMailer(WebhookRegistrar $registrar, string $host, string $key, bool $enabled, string $path, array $values): void
    {
        if (! $enabled) {
            $result = $registrar->unregister($host, $key);

            if ($result === true) {
                $this->components->info('Impostazioni webhook azzerate sul mailer.');
            } else {
                $this->components->warn('Impostazioni webhook non azzerate sul mailer.');
                $this->line('  '.$result);
            }

            return;
        }

        $settings = WebhookSettings::normalize([
            'enabled' => true,
            'path' => $path,
            'url' => $values['CUSTOM_MAILER_WEBHOOK_URL'] ?? null,
            'token' => $values['CUSTOM_MAILER_WEBHOOK_TOKEN'] ?? null,
            'secret' => $values['CUSTOM_MAILER_WEBHOOK_SECRET'] ?? null,
            'signature_header' => $values['CUSTOM_MAILER_WEBHOOK_SIGNATURE_HEADER'] ?? null,
        ]);

        $result = $registrar->register($host, $key, $settings);

        if ($result === true) {
            $this->components->info('Impostazioni webhook salvate sul mailer: '.WebhookSettings::url($settings));

            return;
        }

        // Su una riga a parte: i componenti della console vanno a capo da
        // soli e spezzerebbero il motivo a meta'.
        $this->components->warn('Impostazioni webhook non salvate sul mailer.');
        $this->line('  '.$result);
        $this->line('  Per riprovare: php artisan optimize:clear && php artisan mailer-transport:register-webhook');
    }

    protected function answer(string $option, string $question, string $default, bool $required = false, string $env = ''): string
    {
        $value = (string) $this->option($option);

        if ($value !== '') {
            return $value;
        }

        if (! $this->input->isInteractive()) {
            return $default;
        }

        return text(label: $question, default: $default, required: $required, hint: $env);
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
