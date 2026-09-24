<?php

namespace AndreaLagaccia\MailerTransport\Events;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The mailer has processed an email sent through ApiTransport.
 *
 * Dispatched by the webhook endpoint for every `email.processed`
 * notification; the application matches `uuid` with the log row it wrote
 * on MessageSent and records the outcome.
 */
class EmailProcessed
{
    use Dispatchable;

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $payload  The whole notification as received.
     */
    public function __construct(
        public readonly string $uuid,
        public readonly string $status,
        public readonly ?string $error = null,
        public readonly ?CarbonInterface $sentAt = null,
        public readonly ?int $attempts = null,
        public readonly ?string $recipient = null,
        public readonly ?string $subject = null,
        public readonly array $payload = [],
    ) {}

    public function isSent(): bool
    {
        return $this->status === self::STATUS_SENT;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
