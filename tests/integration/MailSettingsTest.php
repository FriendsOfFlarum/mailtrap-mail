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

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MailSettingsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-mailtrap-mail');
    }

    protected function mailSettings(): array
    {
        $response = $this->send(
            $this->request('GET', '/api/mail/settings', [
                'authenticatedAs' => 1,
            ])
        );

        $body = $response->getBody()->getContents();

        $this->assertEquals(200, $response->getStatusCode(), $body);

        return json_decode($body, true)['data']['attributes'];
    }

    #[Test]
    public function mailtrap_driver_is_offered_with_its_fields(): void
    {
        $fields = $this->mailSettings()['fields'];

        $this->assertArrayHasKey('mailtrap', $fields);
        $this->assertSame(
            ['mail_mailtrap_api_token', 'mail_mailtrap_mode', 'mail_mailtrap_inbox_id', 'mail_mailtrap_category'],
            array_keys($fields['mailtrap'])
        );
        $this->assertSame(['sending', 'sandbox'], array_keys($fields['mailtrap']['mail_mailtrap_mode']));
    }

    #[Test]
    public function an_incomplete_sandbox_setup_is_reported_on_the_mail_page(): void
    {
        $this->setting('mail_driver', 'mailtrap');
        $this->setting('mail_mailtrap_api_token', 'a1b2c3d4');
        $this->setting('mail_mailtrap_mode', 'sandbox');

        $attributes = $this->mailSettings();

        $this->assertFalse($attributes['sending']);
        $this->assertArrayHasKey('mail_mailtrap_inbox_id', $attributes['errors']);
    }

    #[Test]
    public function a_complete_setup_is_reported_as_sending(): void
    {
        $this->setting('mail_driver', 'mailtrap');
        $this->setting('mail_mailtrap_api_token', 'a1b2c3d4');

        $attributes = $this->mailSettings();

        $this->assertTrue($attributes['sending']);
        $this->assertSame([], $attributes['errors']);
    }
}
