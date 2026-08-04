<?php

namespace AndreaLagaccia\MailerTransport;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class MailerTransportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/mailer-transport.php', 'mailer-transport');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/mailer-transport.php' => config_path('mailer-transport.php'),
        ], 'mailer-transport-config');

        $config = $this->app['config'];
        $name = $config->get('mailer-transport.name');

        $config->set("mail.mailers.{$name}", array_merge(
            [
                'transport' => $name,
                'host' => $config->get('mailer-transport.host'),
                'api_key' => $config->get('mailer-transport.api_key'),
                'sync' => $config->get('mailer-transport.sync', false),
            ],
            $config->get("mail.mailers.{$name}", [])
        ));

        Mail::extend($name, function (array $config) {
            return new ApiTransport(
                $config['host'],
                $config['api_key'],
                $config['sync'] ?? false,
                $config['transport'],
            );
        });
    }
}
