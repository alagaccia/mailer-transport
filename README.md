# Mailer Transport for Laravel

Custom Laravel mail transport that delivers messages through a mailer HTTP API.

The package registers the mailer automatically (service provider auto-discovery + config injection into `mail.mailers.{name}`), so no manual changes to `config/mail.php` or `AppServiceProvider` are needed.

## Installation

```bash
composer require alagaccia/mailer-transport
```

## Configuration

Set these variables in your `.env`:

```dotenv
MAIL_MAILER=custom

CUSTOM_MAILER_HOST=https://mailer.example.com/api/send
CUSTOM_MAILER_KEY=your-api-key
# Optional: mailer/transport name (default "custom")
CUSTOM_MAILER_NAME=custom
# Optional: process the message synchronously on the mailer (default false)
CUSTOM_MAILER_SYNC=false
```

That's it. Every mail sent by Laravel (`Mail::send()`, Mailables, notifications, …) is delivered as a JSON `POST` to `CUSTOM_MAILER_HOST` authenticated with the `X-API-KEY` header.

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
    "to": ["recipient@example.com"],
    "subject": "Subject",
    "body": "<p>HTML body (falls back to text body)</p>",
    "sync": false,
    "attachments": [
        { "filename": "file.pdf", "content": "<base64>", "mime": "application/pdf" }
    ]
}
```

`attachments` is present only when the message has attachments.

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
