<?php

namespace AndreaLagaccia\MailerTransport\Http\Requests;

use AndreaLagaccia\MailerTransport\Events\EmailProcessed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookRequest extends FormRequest
{
    /**
     * An email has been processed by the mailer.
     */
    public const EVENT_PROCESSED = 'email.processed';

    /**
     * Test notification fired from the mailer panel.
     */
    public const EVENT_TEST = 'webhook.test';

    /**
     * Access is already filtered by VerifyWebhookSignature.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'event' => ['required', 'string', Rule::in([self::EVENT_PROCESSED, self::EVENT_TEST])],
            'uuid' => ['nullable', 'required_if:event,'.self::EVENT_PROCESSED, 'uuid'],
            'status' => ['required', 'string', Rule::in([EmailProcessed::STATUS_SENT, EmailProcessed::STATUS_FAILED])],
            'error' => ['nullable', 'string'],
            'sent_at' => ['nullable', 'date'],
            'attempts' => ['nullable', 'integer'],
            'recipient' => ['nullable', 'string'],
            'subject' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'event' => 'evento',
            'uuid' => 'identificativo dell\'email',
            'status' => 'esito',
            'error' => 'errore',
            'sent_at' => 'data di invio',
            'attempts' => 'tentativi',
            'recipient' => 'destinatario',
            'subject' => 'oggetto',
        ];
    }
}
