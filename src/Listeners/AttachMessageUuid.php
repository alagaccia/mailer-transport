<?php

namespace AndreaLagaccia\MailerTransport\Listeners;

use AndreaLagaccia\MailerTransport\ApiTransport;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Str;

/**
 * Gives every outgoing message its uuid BEFORE it is sent.
 *
 * ApiTransport would generate one anyway, but only while sending: listeners
 * on MessageSending (an email log written before delivery, for instance)
 * would not see it. Registered by the service provider for every mailer, so
 * the application finds the same header regardless of the transport in use.
 */
class AttachMessageUuid
{
    public function handle(MessageSending $event): void
    {
        $headers = $event->message->getHeaders();
        $header = $headers->get(ApiTransport::UUID_HEADER);

        if ($header !== null && $header->getBodyAsString() !== '') {
            return;
        }

        $headers->addTextHeader(ApiTransport::UUID_HEADER, (string) Str::uuid());
    }
}
