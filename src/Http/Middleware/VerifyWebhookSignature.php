<?php

namespace AndreaLagaccia\MailerTransport\Http\Middleware;

use AndreaLagaccia\MailerTransport\WebhookSettings;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the mailer's notifications with the same credentials the
 * transport announced in the payload: the token (sent back as X-API-KEY and
 * as a Bearer token) and the HMAC SHA-256 signature of the raw body, sent as
 * `sha256=<hex>` in the configured header. Each configured credential is
 * enforced; with none configured the endpoint stays closed.
 */
class VerifyWebhookSignature
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = WebhookSettings::current();

        $reason = $this->rejectionReason($request, $settings['token'], $settings['secret'], $settings['signature_header']);

        if ($reason !== null) {
            Log::warning('Mailer webhook rejected.', [
                'ip' => $request->ip(),
                'reason' => $reason,
            ]);

            return new JsonResponse(['message' => 'Non autorizzato.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }

    protected function rejectionReason(Request $request, ?string $token, ?string $secret, string $signatureHeader): ?string
    {
        if ($token === null && $secret === null) {
            return 'nessun token o segreto configurato in mailer-transport.webhook';
        }

        if ($token !== null) {
            $provided = (string) $request->header('X-API-KEY', '');

            if ($provided === '') {
                $provided = (string) $request->bearerToken();
            }

            if ($provided === '') {
                return 'token di autenticazione assente';
            }

            if (! hash_equals($token, $provided)) {
                return 'token di autenticazione diverso da quello atteso';
            }
        }

        if ($secret !== null) {
            $provided = (string) $request->header($signatureHeader, '');

            if ($provided === '') {
                return 'intestazione della firma assente';
            }

            if (! hash_equals(WebhookSettings::sign($request->getContent(), $secret), $provided)) {
                return 'firma diversa da quella attesa';
            }
        }

        return null;
    }
}
