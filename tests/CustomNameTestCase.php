<?php

namespace AndreaLagaccia\MailerTransport\Tests;

class CustomNameTestCase extends TestCase
{
    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('mailer-transport.name', 'mio-mailer');
        $app['config']->set('mail.default', 'mio-mailer');
    }
}
