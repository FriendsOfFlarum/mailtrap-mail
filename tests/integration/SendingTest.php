<?php

/*
 * This file is part of fof/mailtrap-mail.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MailtrapMail\Tests\integration;

use Flarum\Extend;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\MailtrapMail\Tests\fixtures\FakeMailtrapApi;
use PHPUnit\Framework\Attributes\Test;

class SendingTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-mailtrap-mail');
        $this->extend((new Extend\ServiceProvider())->register(FakeMailtrapApi::class));

        $this->setting('mail_driver', 'mailtrap');
        $this->setting('mail_from', 'forum@example.com');
        $this->setting('mail_mailtrap_api_token', 'a1b2c3d4');
    }

    protected function sendTestMail(): void
    {
        $response = $this->send(
            $this->request('POST', '/api/mail/test', [
                'authenticatedAs' => 1,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function the_test_mail_is_sent_through_the_mailtrap_sending_api(): void
    {
        $this->sendTestMail();

        $this->assertCount(1, FakeMailtrapApi::$requests);
        $this->assertSame('https://send.api.mailtrap.io/api/send', FakeMailtrapApi::$requests[0]['url']);
        $this->assertSame([['email' => 'admin@machine.local']], FakeMailtrapApi::$requests[0]['body']['to']);
    }

    #[Test]
    public function the_configured_category_is_sent_to_mailtrap(): void
    {
        $this->setting('mail_mailtrap_category', 'Forum mail');

        $this->sendTestMail();

        $this->assertSame('Forum mail', FakeMailtrapApi::$requests[0]['body']['category'] ?? null);
    }

    #[Test]
    public function sandbox_mode_delivers_the_test_mail_into_the_inbox(): void
    {
        $this->setting('mail_mailtrap_mode', 'sandbox');
        $this->setting('mail_mailtrap_inbox_id', '1234567');

        $this->sendTestMail();

        $this->assertCount(1, FakeMailtrapApi::$requests);
        $this->assertSame('https://sandbox.api.mailtrap.io/api/send/1234567', FakeMailtrapApi::$requests[0]['url']);
    }

    #[Test]
    public function nothing_is_sent_while_sandbox_mode_lacks_an_inbox(): void
    {
        $this->setting('mail_mailtrap_mode', 'sandbox');

        $this->sendTestMail();

        $this->assertCount(0, FakeMailtrapApi::$requests);
    }
}
