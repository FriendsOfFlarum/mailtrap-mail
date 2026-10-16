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
use FoF\MailtrapMail\Extend\Mailtrap;
use FoF\MailtrapMail\Tests\fixtures\FakeMailtrapApi;
use PHPUnit\Framework\Attributes\Test;

class ConfiguringInCodeTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-mailtrap-mail');
        $this->extend((new Extend\ServiceProvider())->register(FakeMailtrapApi::class));

        $this->setting('mail_from', 'forum@example.com');
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
    public function force_makes_mailtrap_the_mail_driver(): void
    {
        $this->setting('mail_driver', 'log');
        $this->setting('mail_mailtrap_api_token', 'a1b2c3d4');

        $this->extend((new Mailtrap())->force());

        $this->sendTestMail();

        $this->assertCount(1, FakeMailtrapApi::$requests);
    }

    #[Test]
    public function a_pinned_api_token_wins_over_the_stored_one(): void
    {
        $this->setting('mail_mailtrap_api_token', 'stored-token');

        $this->extend((new Mailtrap())->force()->apiToken('pinned-token'));

        $this->sendTestMail();

        $this->assertContains('Authorization: Bearer pinned-token', FakeMailtrapApi::$requests[0]['headers']);
    }

    #[Test]
    public function a_pinned_sandbox_inbox_receives_the_mail(): void
    {
        $this->setting('mail_mailtrap_mode', 'sending');

        $this->extend((new Mailtrap())->force()->apiToken('a1b2c3d4')->mode('sandbox')->inboxId(1234567));

        $this->sendTestMail();

        $this->assertSame('https://sandbox.api.mailtrap.io/api/send/1234567', FakeMailtrapApi::$requests[0]['url']);
    }

    #[Test]
    public function a_pinned_category_is_sent_to_mailtrap(): void
    {
        $this->setting('mail_mailtrap_category', 'Stored category');

        $this->extend((new Mailtrap())->force()->apiToken('a1b2c3d4')->category('CI'));

        $this->sendTestMail();

        $this->assertSame('CI', FakeMailtrapApi::$requests[0]['body']['category'] ?? null);
    }

    #[Test]
    public function an_unset_value_leaves_the_stored_setting_in_place(): void
    {
        $this->setting('mail_mailtrap_api_token', 'stored-token');

        // e.g. ->apiToken(getenv('MAILTRAP_API_TOKEN') ?: null) where the variable is not set
        $this->extend((new Mailtrap())->force()->apiToken(null));

        $this->sendTestMail();

        $this->assertContains('Authorization: Bearer stored-token', FakeMailtrapApi::$requests[0]['headers']);
    }

    protected function adminPayload(): array
    {
        $response = $this->send(
            $this->request('GET', '/admin', ['authenticatedAs' => 1])
        );

        preg_match('/<script id="flarum-json-payload" type="application\/json">(.+?)<\/script>/s', (string) $response->getBody(), $matches);

        $this->assertNotEmpty($matches, 'The admin page carried no JSON payload.');

        return json_decode(html_entity_decode($matches[1]), true);
    }

    #[Test]
    public function the_admin_page_is_told_which_settings_are_pinned(): void
    {
        $this->extend((new Mailtrap())->force()->mode('sandbox')->inboxId(1234567)->apiToken(null));

        $this->assertEqualsCanonicalizing(
            ['mail_driver', 'mail_mailtrap_mode', 'mail_mailtrap_inbox_id'],
            $this->adminPayload()['fof-mailtrap-mail.pinned'] ?? null
        );
    }

    #[Test]
    public function nothing_is_pinned_without_the_extender(): void
    {
        $this->assertSame([], $this->adminPayload()['fof-mailtrap-mail.pinned'] ?? null);
    }

    #[Test]
    public function a_pinned_api_token_never_reaches_the_admin_page(): void
    {
        $this->extend((new Mailtrap())->force()->apiToken('pinned-secret-token'));

        $response = $this->send(
            $this->request('GET', '/admin', ['authenticatedAs' => 1])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringNotContainsString('pinned-secret-token', (string) $response->getBody());
    }
}
