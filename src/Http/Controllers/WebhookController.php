<?php

namespace AndreaLagaccia\MailerTransport\Http\Controllers;

use AndreaLagaccia\MailerTransport\Events\EmailProcessed;
use AndreaLagaccia\MailerTransport\Http\Requests\WebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;

/**
 * Receives the mailer's notifications and turns them into EmailProcessed
 * events: what to do with the outcome is up to the application's listeners.
 *
 * A notification about an unknown email still gets a 200: whether the uuid
 * belongs to this application is for the listener to decide, and a "not
 * ours" is not an error worth ending up in the mailer's log.
 */
class WebhookController
{
    public function __invoke(WebhookRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data['event'] === WebhookRequest::EVENT_TEST) {
            return new JsonResponse(['message' => 'Notifica di prova ricevuta.']);
        }

        EmailProcessed::dispatch(
            $data['uuid'],
            $data['status'],
            $data['error'] ?? null,
            isset($data['sent_at']) ? Date::parse($data['sent_at']) : null,
            isset($data['attempts']) ? (int) $data['attempts'] : null,
            $data['recipient'] ?? null,
            $data['subject'] ?? null,
            $request->all(),
        );

        return new JsonResponse(['message' => 'Esito registrato.']);
    }
}
