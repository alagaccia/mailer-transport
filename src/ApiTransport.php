<?php

namespace AndreaLagaccia\MailerTransport;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

class ApiTransport extends AbstractTransport
{
    public function __construct(
        protected string $host,
        protected string $apiKey,
        public bool $sync = false,
        protected string $name = 'custom',
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $recipients = array_map(
            static fn ($address) => $address->getAddress(),
            $email->getTo()
        );

        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'filename' => $attachment->getFilename(),
                'content' => base64_encode($attachment->getBody()),
                'mime' => $attachment->getContentType(),
            ];
        }

        $payload = [
            'to' => $recipients,
            'subject' => $email->getSubject(),
            'body' => $email->getHtmlBody() ?: $email->getTextBody(),
            'sync' => $this->sync,
        ];

        if (! empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        $response = Http::withHeaders([
            'X-API-KEY' => $this->apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post($this->host, $payload);

        if ($response->failed()) {
            throw new TransportException('Errore invio tramite Mailer API: '.$response->body());
        }
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
