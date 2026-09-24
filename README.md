# Mailer Transport for Laravel

Custom Laravel mail transport that delivers messages through a mailer HTTP API.

The package registers the mailer automatically (service provider auto-discovery + config injection into `mail.mailers.{name}`), so no manual changes to `config/mail.php` or `AppServiceProvider` are needed.

## Installation

```bash
composer require alagaccia/mailer-transport
```

## Configuration

The quickest way is the install command, which publishes the config file and writes every setting to `.env` (asking for what is missing, generating the HMAC secret when you leave it empty):

```bash
php artisan mailer-transport:install
```

At the end it offers to save the same webhook settings on the mailer's *Settings > Webhook* page too (`PUT /api/webhook`, authenticated with the API key just given), so the mailer is configured in one go. Every value can also be given as an option for unattended setups (`--host`, `--key`, `--webhook-secret=generate`, `--webhook-token`, `--webhook-header`, `--webhook-url`, `--no-webhook`, `--register`, `--default-mailer`, `--no-publish`).

After editing the `CUSTOM_MAILER_WEBHOOK_*` variables by hand, `php artisan mailer-transport:register-webhook` saves them on the mailer again (`--remove` clears them there).

Or set the variables in your `.env` by hand:

```dotenv
MAIL_MAILER=custom

CUSTOM_MAILER_HOST=https://mailer.example.com/api/send
CUSTOM_MAILER_KEY=your-api-key
# Optional: mailer/transport name (default "custom")
CUSTOM_MAILER_NAME=custom
# Optional: process the message synchronously on the mailer (default false)
CUSTOM_MAILER_SYNC=false

# Webhook with which the mailer reports the outcome of every email
CUSTOM_MAILER_WEBHOOK_ENABLED=true
CUSTOM_MAILER_WEBHOOK_SECRET=a-long-random-string
CUSTOM_MAILER_WEBHOOK_SIGNATURE_HEADER=X-Signature
# Optional: extra token, sent back as X-API-KEY / Bearer
CUSTOM_MAILER_WEBHOOK_TOKEN=
# Public URL of the endpoint announced to the mailer (empty = APP_URL + path;
# the install command writes the resolved value) and its path
CUSTOM_MAILER_WEBHOOK_URL=https://app.example.com/api/mailer/webhook
CUSTOM_MAILER_WEBHOOK_PATH=api/mailer/webhook
```

That's it. Every mail sent by Laravel (`Mail::send()`, Mailables, notifications, …) is delivered as a JSON `POST` to `CUSTOM_MAILER_HOST` authenticated with the `X-API-KEY` header.

## Webhook: the outcome of every email

The package registers `POST /api/mailer/webhook` (route `mailer-transport.webhook`) and announces its full URL, the token, the HMAC secret and the signature header in the payload of every message. The mailer calls it back once the email has been processed, with nothing to configure on its own panel.

Incoming notifications are authenticated by `AndreaLagaccia\MailerTransport\Http\Middleware\VerifyWebhookSignature` with the same values: the token (as `X-API-KEY` or `Authorization: Bearer`) and the `sha256=<hex>` HMAC of the raw body. Every configured credential is enforced; with neither configured the endpoint answers `401` to everything.

A valid `email.processed` notification is turned into the `AndreaLagaccia\MailerTransport\Events\EmailProcessed` event (`uuid`, `status`, `error`, `sentAt`, `attempts`, `recipient`, `subject`, `payload`, plus `isSent()` / `isFailed()`). Listen to it to record the outcome on your own email log:

```php
use AndreaLagaccia\MailerTransport\Events\EmailProcessed;

class RecordMailerOutcome
{
    public function handle(EmailProcessed $event): void
    {
        $log = EmailLog::where('uuid', $event->uuid)->first();

        if ($log === null) {
            return; // not one of ours: the mailer serves several applications
        }

        $event->isSent()
            ? $log->markAsSent($event->sentAt)
            : $log->markAsFailed($event->error);
    }
}
```

The `webhook.test` notification fired from the mailer panel is acknowledged with a `200` and dispatches nothing.

## Message uuid

Every message gets a uuid in the `X-Metadata-uuid` header (`ApiTransport::UUID_HEADER`) as soon as `MessageSending` fires, whatever the transport in use, and that same uuid is sent to the mailer and reported back by the webhook. Read it from `MessageSending` or `MessageSent` to key your email log:

```php
$uuid = $event->message->getHeaders()->get(ApiTransport::UUID_HEADER)?->getBodyAsString();
```

## Mailable metadata

`AndreaLagaccia\MailerTransport\Mail\HasMailMetadata` gives a Mailable an `email_type` code (snake case of the class name without `Mail`, or the `$emailTypeCode` property) and the model it is about (`withEmailable($model)`, or the first Eloquent model received by the constructor, excluding the classes returned by `emailableExcludedClasses()`). Merge them into the envelope metadata and they travel as `X-Metadata-*` headers:

```php
public function envelope(): Envelope
{
    return new Envelope(
        subject: 'Order confirmed',
        metadata: $this->mergeMailMetadata(['user_id' => $this->user->id]),
    );
}
```

### Customizing the mailer name

The mailer name defaults to `custom`. To use a different one, set `CUSTOM_MAILER_NAME` (and `MAIL_MAILER` to the same value):

```dotenv
MAIL_MAILER=acme
CUSTOM_MAILER_NAME=acme
```

Alternatively, publish the config file and edit it:

```bash
php artisan vendor:publish --tag=mailer-transport-config
```

## Payload

```json
{
    "uuid": "9f1c2b7e-5d3a-4a8b-9f2e-6c1d7a4b3e50",
    "to": ["recipient@example.com"],
    "subject": "Subject",
    "body": "<p>HTML body (falls back to text body)</p>",
    "sync": false,
    "attachments": [
        { "filename": "file.pdf", "content": "<base64>", "mime": "application/pdf" }
    ],
    "webhook": "https://app.example.com/api/mailer/webhook",
    "webhook_token": "optional token",
    "webhook_secret": "the HMAC secret",
    "webhook_signature_header": "X-Signature"
}
```

`attachments` is present only when the message has attachments; the `webhook*` keys only when the webhook is enabled (`webhook_token` when a token is configured, `webhook_secret` and `webhook_signature_header` when a secret is).

## Extending the transport

`ApiTransport` builds the request in overridable steps (`buildPayload()`, `recipients()`, `attachments()`, `webhookPayload()`, `post()`, `handleFailedResponse()`), so a subclass can add or drop keys without duplicating `doSend()`:

```php
class BrandedTransport extends ApiTransport
{
    protected function buildPayload(Email $email): array
    {
        return [...parent::buildPayload($email), 'smtp' => $this->smtp];
    }
}
```

## Overriding the defaults

Any key defined under `mailers.{name}` in your application's `config/mail.php` takes precedence over the values injected by the package:

```php
'custom' => [
    'transport' => 'custom',
    'host' => env('CUSTOM_MAILER_HOST'),
    'api_key' => env('CUSTOM_MAILER_KEY'),
    'sync' => true,
],
```

## Testing

```bash
composer test
```
