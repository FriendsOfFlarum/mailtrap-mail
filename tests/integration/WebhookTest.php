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

use Flarum\Mail\EmailBounceEvent;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class WebhookTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const SECRET = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-mailtrap-mail');
        $this->setting('mail_driver', 'mailtrap');
        $this->setting('mail_mailtrap_api_token', 'test-token');
        $this->setting('mail_mailtrap_mode', 'sending');
        $this->setting('mail_mailtrap_webhook_signing_secret', self::SECRET);
        $this->prepareDatabase(['users' => [$this->normalUser(), [
            'id' => 3, 'username' => 'second', 'email' => 'second@example.com',
            'is_email_confirmed' => 1, 'password' => 'unused',
        ]]]);
    }

    private function event(string $type = 'bounce', string $email = 'normal@machine.local'): array
    {
        return ['event' => $type, 'email' => $email, 'message_id' => 'message-1',
            'event_id' => 'event-'.$type.'-'.$email, 'timestamp' => time(), 'reason' => 'Unverified sender',
            'response' => 'Mailbox /café/ unavailable'];
    }

    private function body(array $events): string
    {
        return json_encode(['events' => $events], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function post(string $raw, ?string $signature = 'valid'): ResponseInterface
    {
        $body = new Stream('php://temp', 'wb+');
        $body->write($raw);
        $body->rewind();
        $request = (new ServerRequest([], [], '/mail/webhook/mailtrap', 'POST'))
            ->withHeader('Content-Type', 'application/json')->withBody($body)->withParsedBody(json_decode($raw, true));
        if ($signature !== null) {
            $request = $request->withHeader('Mailtrap-Signature', $signature === 'valid' ? hash_hmac('sha256', $raw, self::SECRET) : $signature);
        }

        return $this->send($request);
    }

    #[Test]
    public function unsigned_incorrectly_signed_and_altered_bodies_are_refused(): void
    {
        $raw = $this->body([$this->event()]);
        foreach ([null, 'wrong'] as $signature) {
            $this->assertEquals(406, $this->post($raw, $signature)->getStatusCode());
        }
        $signature = hash_hmac('sha256', $raw, self::SECRET);
        $this->assertEquals(406, $this->post(str_replace('message-1', 'message-2', $raw), $signature)->getStatusCode());
        $this->assertEquals(0, EmailBounceEvent::count());
        $this->assertFalse(User::find(2)->hasBouncedEmail());
    }

    #[Test]
    public function signing_uses_the_exact_raw_bytes_and_batches_are_idempotent(): void
    {
        $raw = $this->body([$this->event(), $this->event('bounce', 'second@example.com')]);
        $response = $this->post($raw);
        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertTrue(User::find(2)->hasBouncedEmail());
        $this->assertTrue(User::find(3)->hasBouncedEmail());
        $this->assertEquals(2, EmailBounceEvent::count());
        User::find(2)->clearEmailBounce()->save();
        $this->assertEquals(200, $this->post($raw)->getStatusCode());
        $this->assertFalse(User::find(2)->hasBouncedEmail());
        $this->assertEquals(2, EmailBounceEvent::count());
    }

    public static function events(): array
    {
        return [['bounce', true], ['spam', true], ['soft bounce', false], ['suspension', false], ['reject', false]];
    }

    #[Test]
    #[DataProvider('events')]
    public function only_recipient_bounces_and_complaints_flag_users(string $event, bool $flags): void
    {
        $this->assertEquals(200, $this->post($this->body([$this->event($event)]))->getStatusCode());
        $this->assertSame($flags, User::find(2)->hasBouncedEmail());
        $this->assertEquals($flags ? 1 : 0, EmailBounceEvent::count());
    }

    #[Test]
    public function missing_secrets_refuse_webhooks(): void
    {
        $this->setting('mail_mailtrap_webhook_signing_secret', '');
        $this->assertEquals(404, $this->post($this->body([$this->event()]))->getStatusCode());
        $this->assertEquals(0, EmailBounceEvent::count());
    }

    #[Test]
    public function a_falsey_signing_secret_never_enables_unsigned_webhooks(): void
    {
        $this->setting('mail_mailtrap_webhook_signing_secret', '0');
        $this->assertEquals(404, $this->post($this->body([$this->event()]), null)->getStatusCode());
        $this->assertEquals(0, EmailBounceEvent::count());
    }

    #[Test]
    public function pinned_secrets_verify_requests_instead_of_the_stored_value(): void
    {
        $pinned = 'fedcba9876543210fedcba9876543210';
        $this->extend((new \FoF\MailtrapMail\Extend\Mailtrap())->webhookSigningSecret($pinned));
        $raw = $this->body([$this->event()]);
        $this->assertEquals(406, $this->post($raw)->getStatusCode());
        $this->assertEquals(200, $this->post($raw, hash_hmac('sha256', $raw, $pinned))->getStatusCode());
        $this->assertTrue(User::find(2)->hasBouncedEmail());
    }

    #[Test]
    public function sandbox_hides_the_url_and_refuses_delivery_events(): void
    {
        $this->setting('mail_mailtrap_mode', 'sandbox');
        $this->setting('mail_mailtrap_inbox_id', '123');
        $response = $this->send($this->request('GET', '/api/mail/settings', ['authenticatedAs' => 1]));
        $this->assertEquals(200, $response->getStatusCode());
        $attributes = json_decode((string) $response->getBody(), true)['data']['attributes'];
        $this->assertFalse($attributes['canReceiveWebhooks']);
        $this->assertNull($attributes['webhookUrl']);
        $this->assertEquals(404, $this->post($this->body([$this->event()]))->getStatusCode());
        $this->assertEquals(0, EmailBounceEvent::count());
    }
}
