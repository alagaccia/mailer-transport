<?php

use AndreaLagaccia\MailerTransport\Tests\CustomNameTestCase;
use AndreaLagaccia\MailerTransport\Tests\TestCase;

uses(TestCase::class)->in('ApiTransportTest.php', 'WebhookTest.php', 'InstallCommandTest.php');
uses(CustomNameTestCase::class)->in('CustomNameTest.php');
