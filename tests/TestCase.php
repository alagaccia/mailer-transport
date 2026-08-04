<?php

namespace AndreaLagaccia\MailerTransport\Tests;

use AndreaLagaccia\MailerTransport\MailerTransportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            MailerTransportServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('mail.default', 'custom');
    }
}
