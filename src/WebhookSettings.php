<?php

namespace AndreaLagaccia\MailerTransport;

/**
 * Webhook settings as announced to the mailer and enforced on the way back.
 *
 * @phpstan-type Settings array{enabled: bool, path: string, url: string|null, token: string|null, secret: string|null, signature_header: string}
 */
class WebhookSettings
{
    public const DEFAULT_PATH = 'api/mailer/webhook';

    public const DEFAULT_SIGNATURE_HEADER = 'X-Signature';

    public const SIGNATURE_ALGO = 'sha256';

    /**
     * Normalizes the raw `mailer-transport.webhook` configuration.
     *
     * @param  array<string, mixed>|null  $config
     * @return Settings
     */
    public static function normalize(?array $config): array
    {
        $config ??= [];

        $path = trim((string) ($config['path'] ?? self::DEFAULT_PATH), '/');
        $header = trim((string) ($config['signature_header'] ?? ''));

        return [
            'enabled' => (bool) ($config['enabled'] ?? true),
            'path' => $path !== '' ? $path : self::DEFAULT_PATH,
            'url' => self::blankToNull($config['url'] ?? null),
            'token' => self::blankToNull($config['token'] ?? null),
            'secret' => self::blankToNull($config['secret'] ?? null),
            'signature_header' => $header !== '' ? $header : self::DEFAULT_SIGNATURE_HEADER,
        ];
    }

    /**
     * The settings currently configured for this application.
     *
     * @return Settings
     */
    public static function current(): array
    {
        return self::normalize(config('mailer-transport.webhook'));
    }

    /**
     * Public URL of the endpoint: the configured override, or APP_URL + path.
     *
     * @param  Settings  $settings
     */
    public static function url(array $settings): ?string
    {
        if ($settings['url'] !== null) {
            return $settings['url'];
        }

        $base = rtrim((string) config('app.url'), '/');

        return $base !== '' ? $base.'/'.$settings['path'] : null;
    }

    /**
     * The keys added to every message sent to the mailer, so that it calls
     * this application back with the right URL, token and signature.
     *
     * @param  Settings  $settings
     * @return array<string, string>
     */
    public static function payload(array $settings): array
    {
        $url = self::url($settings);

        if (! $settings['enabled'] || $url === null) {
            return [];
        }

        $payload = ['webhook' => $url];

        if ($settings['token'] !== null) {
            $payload['webhook_token'] = $settings['token'];
        }

        if ($settings['secret'] !== null) {
            $payload['webhook_secret'] = $settings['secret'];
            $payload['webhook_signature_header'] = $settings['signature_header'];
        }

        return $payload;
    }

    /**
     * Signature of a raw body, in the form the mailer sends it.
     */
    public static function sign(string $body, string $secret): string
    {
        return self::SIGNATURE_ALGO.'='.hash_hmac(self::SIGNATURE_ALGO, $body, $secret);
    }

    private static function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
