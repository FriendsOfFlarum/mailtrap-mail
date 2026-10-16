<?php

/*
 * This file is part of fof/mailtrap-mail.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MailtrapMail\Tests\unit;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\unit\TestCase;
use FoF\MailtrapMail\MailtrapDriver;
use Illuminate\Support\MessageBag;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator as ValidationTranslator;
use Illuminate\Validation\Factory as ValidatorFactory;
use Mailtrap\Mime\MailtrapEmail;
use Mockery as m;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class MailtrapDriverTest extends TestCase
{
    /** @var array<int, array{method: string, url: string, headers: array<int, string>, body: array<string, mixed>}> */
    private array $requests = [];

    private ?JsonMockResponse $nextResponse = null;

    private function mailtrapApi(): MockHttpClient
    {
        return new MockHttpClient(function (string $method, string $url, array $options) {
            $this->requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $options['headers'],
                'body' => json_decode($options['body'], true),
            ];

            return $this->nextResponse ?? new JsonMockResponse(['success' => true, 'message_ids' => ['0c7fd939-02cf-11ed-88c2-0a58a9feac02']]);
        });
    }

    private function email(): Email
    {
        return (new Email())
            ->from('forum@example.com')
            ->to('member@example.org')
            ->subject('Reset your password')
            ->text('Follow the link to reset your password.')
            ->html('<p>Follow the link to reset your password.</p>');
    }

    private function driver(): MailtrapDriver
    {
        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__.'/../../locale/en.yml', 'en');

        return new MailtrapDriver($translator, $this->mailtrapApi());
    }

    private function settings(array $values): SettingsRepositoryInterface
    {
        $settings = m::mock(SettingsRepositoryInterface::class);
        $settings->shouldReceive('all')->andReturn($values);
        $settings->shouldReceive('get')->andReturnUsing(fn ($key, $default = null) => $values[$key] ?? $default);

        return $settings;
    }

    private function validate(array $values): MessageBag
    {
        $validator = new ValidatorFactory(new ValidationTranslator(new ArrayLoader(), 'en'));

        return $this->driver()->validate($this->settings($values), $validator);
    }

    #[Test]
    public function it_offers_token_mode_inbox_and_category_fields(): void
    {
        $this->assertSame([
            'mail_mailtrap_api_token' => '',
            'mail_mailtrap_mode' => [
                'sending' => 'Send email',
                'sandbox' => 'Sandbox (testing)',
            ],
            'mail_mailtrap_inbox_id' => '',
            'mail_mailtrap_category' => '',
        ], $this->driver()->availableSettings());
    }

    #[Test]
    public function it_can_send(): void
    {
        $this->assertTrue($this->driver()->canSend());
    }

    #[Test]
    public function validation_fails_without_an_api_token(): void
    {
        $errors = $this->validate(['mail_mailtrap_mode' => 'sending']);

        $this->assertTrue($errors->has('mail_mailtrap_api_token'));
    }

    #[Test]
    public function validation_fails_when_the_api_token_contains_whitespace(): void
    {
        $errors = $this->validate(['mail_mailtrap_api_token' => ' a1b2c3d4 ', 'mail_mailtrap_mode' => 'sending']);

        $this->assertTrue($errors->has('mail_mailtrap_api_token'));
    }

    #[Test]
    public function bulk_is_not_a_mode(): void
    {
        $errors = $this->validate(['mail_mailtrap_api_token' => 'a1b2c3d4', 'mail_mailtrap_mode' => 'bulk']);

        $this->assertTrue($errors->has('mail_mailtrap_mode'));
    }

    #[Test]
    public function sandbox_mode_requires_an_inbox_id(): void
    {
        $errors = $this->validate(['mail_mailtrap_api_token' => 'a1b2c3d4', 'mail_mailtrap_mode' => 'sandbox', 'mail_mailtrap_inbox_id' => '']);

        $this->assertTrue($errors->has('mail_mailtrap_inbox_id'));
    }

    #[Test]
    public function the_inbox_id_must_be_numeric(): void
    {
        $errors = $this->validate(['mail_mailtrap_api_token' => 'a1b2c3d4', 'mail_mailtrap_mode' => 'sandbox', 'mail_mailtrap_inbox_id' => 'My Inbox']);

        $this->assertTrue($errors->has('mail_mailtrap_inbox_id'));
    }

    public static function validConfigurations(): array
    {
        return [
            'sending, inbox left blank' => [['mail_mailtrap_api_token' => 'a1b2c3d4', 'mail_mailtrap_mode' => 'sending', 'mail_mailtrap_inbox_id' => '', 'mail_mailtrap_category' => '']],
            'sending with a category' => [['mail_mailtrap_api_token' => 'a1b2c3d4', 'mail_mailtrap_mode' => 'sending', 'mail_mailtrap_inbox_id' => '', 'mail_mailtrap_category' => 'Forum mail']],
            'sandbox with an inbox' => [['mail_mailtrap_api_token' => 'a1b2c3d4', 'mail_mailtrap_mode' => 'sandbox', 'mail_mailtrap_inbox_id' => '1234567']],
        ];
    }

    #[Test]
    #[DataProvider('validConfigurations')]
    public function a_complete_configuration_is_valid(array $values): void
    {
        $this->assertSame([], $this->validate($values)->toArray());
    }

    #[Test]
    public function the_category_is_limited_to_255_characters(): void
    {
        $errors = $this->validate(['mail_mailtrap_api_token' => 'a1b2c3d4', 'mail_mailtrap_mode' => 'sending', 'mail_mailtrap_category' => str_repeat('a', 256)]);

        $this->assertTrue($errors->has('mail_mailtrap_category'));
    }

    #[Test]
    public function sending_mode_sends_through_the_sending_api(): void
    {
        $transport = $this->driver()->buildTransport($this->settings([
            'mail_mailtrap_api_token' => 'a1b2c3d4',
            'mail_mailtrap_mode' => 'sending',
        ]));

        $transport->send($this->email());

        $this->assertCount(1, $this->requests);
        $this->assertSame('POST', $this->requests[0]['method']);
        $this->assertSame('https://send.api.mailtrap.io/api/send', $this->requests[0]['url']);
        $this->assertContains('Authorization: Bearer a1b2c3d4', $this->requests[0]['headers']);
        $this->assertSame(['email' => 'forum@example.com'], $this->requests[0]['body']['from']);
        $this->assertSame([['email' => 'member@example.org']], $this->requests[0]['body']['to']);
        $this->assertSame('Reset your password', $this->requests[0]['body']['subject']);
        $this->assertSame('Follow the link to reset your password.', $this->requests[0]['body']['text']);
        $this->assertSame('<p>Follow the link to reset your password.</p>', $this->requests[0]['body']['html']);
        $this->assertArrayNotHasKey('category', $this->requests[0]['body']);
    }


    #[Test]
    public function sandbox_mode_delivers_into_the_configured_inbox(): void
    {
        $transport = $this->driver()->buildTransport($this->settings([
            'mail_mailtrap_api_token' => 'a1b2c3d4',
            'mail_mailtrap_mode' => 'sandbox',
            'mail_mailtrap_inbox_id' => '1234567',
        ]));

        $transport->send($this->email());

        $this->assertSame('https://sandbox.api.mailtrap.io/api/send/1234567', $this->requests[0]['url']);
        $this->assertContains('Authorization: Bearer a1b2c3d4', $this->requests[0]['headers']);
    }

    #[Test]
    public function a_rejected_send_reports_mailtraps_reason(): void
    {
        $this->nextResponse = new JsonMockResponse(['success' => false, 'errors' => ['Unauthorized']], ['http_code' => 401]);

        $transport = $this->driver()->buildTransport($this->settings([
            'mail_mailtrap_api_token' => 'revoked-token',
            'mail_mailtrap_mode' => 'sending',
        ]));

        $this->expectException(TransportExceptionInterface::class);
        $this->expectExceptionMessage('Unauthorized');

        $transport->send($this->email());
    }

    #[Test]
    public function the_configured_category_is_sent_with_each_email(): void
    {
        $transport = $this->driver()->buildTransport($this->settings([
            'mail_mailtrap_api_token' => 'a1b2c3d4',
            'mail_mailtrap_mode' => 'sending',
            'mail_mailtrap_category' => 'Forum mail',
        ]));

        $transport->send($this->email());

        $this->assertSame('Forum mail', $this->requests[0]['body']['category']);
    }

    #[Test]
    public function a_category_already_on_the_email_is_kept(): void
    {
        $transport = $this->driver()->buildTransport($this->settings([
            'mail_mailtrap_api_token' => 'a1b2c3d4',
            'mail_mailtrap_mode' => 'sending',
            'mail_mailtrap_category' => 'Forum mail',
        ]));

        $email = (new MailtrapEmail())
            ->from('forum@example.com')
            ->to('member@example.org')
            ->subject('Weekly digest')
            ->text('This week on the forum.')
            ->category('Digest');

        $transport->send($email);

        $this->assertSame('Digest', $this->requests[0]['body']['category']);
    }
}
