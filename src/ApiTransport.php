<?php

namespace AndreaLagaccia\MailerTransport;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

class ApiTransport extends AbstractTransport
{
    /**
     * Header con cui l'uuid del messaggio viene esposto all'applicazione.
     */
    public const UUID_HEADER = 'X-Metadata-uuid';

    /**
     * @param  array<string, mixed>  $webhook  Raw `mailer-transport.webhook` configuration.
     */
    public function __construct(
        protected string $host,
        protected string $apiKey,
        public bool $sync = false,
        protected string $name = 'custom',
        protected array $webhook = [],
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $response = $this->post($this->buildPayload($email));

        if ($response->failed()) {
            $this->handleFailedResponse($response);
        }
    }

    /**
     * The JSON body sent to the mailer. Subclasses extend it (or drop keys)
     * by overriding this method instead of duplicating doSend().
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(Email $email): array
    {
        $payload = [
            'uuid' => $this->resolveUuid($email),
            'to' => $this->recipients($email),
            'subject' => $email->getSubject(),
            'body' => $email->getHtmlBody() ?: $email->getTextBody(),
            'sync' => $this->sync,
        ];

        $attachments = $this->attachments($email);

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        return array_merge($payload, $this->webhookPayload());
    }

    /**
     * @return list<string>
     */
    protected function recipients(Email $email): array
    {
        return array_map(
            static fn ($address) => $address->getAddress(),
            $email->getTo()
        );
    }

    /**
     * @return list<array{filename: string|null, content: string, mime: string}>
     */
    protected function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'filename' => $attachment->getFilename(),
                'content' => base64_encode($attachment->getBody()),
                'mime' => $attachment->getContentType(),
            ];
        }

        return $attachments;
    }

    /**
     * URL and credentials of this application's webhook, so the mailer can
     * report the outcome without any configuration on its own panel.
     *
     * @return array<string, string>
     */
    protected function webhookPayload(): array
    {
        return WebhookSettings::payload(WebhookSettings::normalize($this->webhook));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function post(array $payload): Response
    {
        return Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->host, $payload);
    }

    protected function handleFailedResponse(Response $response): never
    {
        throw new TransportException('Errore invio tramite Mailer API: '.$response->body());
    }

    /**
     * Restituisce l'uuid del messaggio, generandolo e scrivendolo nell'header
     * del messaggio stesso in modo che l'applicazione possa recuperarlo
     * dall'evento MessageSent.
     */
    protected function resolveUuid(Email $email): string
    {
        $headers = $email->getHeaders();
        $header = $headers->get(self::UUID_HEADER);

        if ($header !== null && $header->getBodyAsString() !== '') {
            return $header->getBodyAsString();
        }

        $uuid = (string) Str::uuid();
        $headers->addTextHeader(self::UUID_HEADER, $uuid);

        return $uuid;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
