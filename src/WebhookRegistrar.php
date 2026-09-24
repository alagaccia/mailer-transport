<?php

namespace AndreaLagaccia\MailerTransport;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Saves this application's webhook settings on the mailer (PUT /api/webhook,
 * the same values as its "Settings > Webhook" page), authenticating with the
 * API key. Meant for a mailer serving this application: the settings are
 * global there, the last application to save them wins.
 */
class WebhookRegistrar
{
    /**
     * The mailer's webhook endpoint, next to the send endpoint:
     * https://mailer.example.com/api/send => https://mailer.example.com/api/webhook
     */
    public static function endpoint(string $host): ?string
    {
        $host = rtrim(trim($host), '/');

        if ($host === '' || ! Str::endsWith($host, '/send')) {
            return null;
        }

        return Str::replaceLast('/send', '/webhook', $host);
    }

    /**
     * @param  array{enabled: bool, path: string, url: string|null, token: string|null, secret: string|null, signature_header: string}  $settings
     * @return true|string true on success, the reason otherwise
     */
    public function register(string $host, string $apiKey, array $settings): true|string
    {
        $url = WebhookSettings::url($settings);

        if ($url === null) {
            return 'URL pubblico del webhook non determinabile: imposta APP_URL o CUSTOM_MAILER_WEBHOOK_URL';
        }

        return $this->call('put', $host, $apiKey, [
            'url' => $url,
            'token' => $settings['token'],
            'secret' => $settings['secret'],
            'signature_header' => $settings['signature_header'],
        ]);
    }

    /**
     * Clears the webhook settings on the mailer.
     *
     * @return true|string true on success, the reason otherwise
     */
    public function unregister(string $host, string $apiKey): true|string
    {
        return $this->call('put', $host, $apiKey, ['url' => null]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function call(string $method, string $host, string $apiKey, array $data = []): true|string
    {
        $endpoint = static::endpoint($host);

        if ($endpoint === null) {
            return "Impossibile ricavare l'endpoint del webhook da CUSTOM_MAILER_HOST (deve terminare con /send)";
        }

        if (trim($apiKey) === '') {
            return 'Chiave API (CUSTOM_MAILER_KEY) non configurata';
        }

        try {
            /** @var Response $response */
            $response = Http::timeout(10)
                ->acceptJson()
                ->withHeaders(['X-API-KEY' => $apiKey])
                ->{$method}($endpoint, $data);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        if ($response->successful()) {
            return true;
        }

        $detail = $response->json('message') ?? $response->json('error');

        return "Il mailer ha risposto {$response->status()}".(is_string($detail) ? ": {$detail}" : '');
    }
}
