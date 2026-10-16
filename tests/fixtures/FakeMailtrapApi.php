<?php

/*
 * This file is part of fof/mailtrap-mail.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MailtrapMail\Tests\fixtures;

use Flarum\Foundation\AbstractServiceProvider;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Stands in for the Mailtrap API inside a booted forum: binds the HTTP client
 * the driver uses to one that records each request and answers like Mailtrap.
 */
class FakeMailtrapApi extends AbstractServiceProvider
{
    /** @var array<int, array{method: string, url: string, headers: array<int, string>, body: array<string, mixed>}> */
    public static array $requests = [];

    public function register(): void
    {
        static::$requests = [];

        $this->container->bind(HttpClientInterface::class, fn () => new MockHttpClient(function (string $method, string $url, array $options) {
            static::$requests[] = [
                'method' => $method,
                'url' => $url,
                'headers' => $options['headers'],
                'body' => json_decode($options['body'], true),
            ];

            return new JsonMockResponse(['success' => true, 'message_ids' => ['0c7fd939-02cf-11ed-88c2-0a58a9feac02']]);
        }));
    }
}
