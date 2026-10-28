<?php

/*
 * This file is part of fof/mailtrap-mail.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MailtrapMail;

use Flarum\Mail\DriverInterface;
use Flarum\Mail\HandlesWebhooks;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\MessageBag;
use Mailtrap\Api\AbstractApi;
use Mailtrap\Api\Sandbox\Emails as SandboxEmails;
use Mailtrap\Api\Sending\Emails as SendingEmails;
use Mailtrap\Config;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Mailer\Bridge\Mailtrap\RemoteEvent\MailtrapPayloadConverter;
use Symfony\Component\Mailer\Bridge\Mailtrap\Webhook\MailtrapRequestParser;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\RemoteEvent\Event\Mailer\MailerDeliveryEvent;
use Symfony\Component\Webhook\Client\RequestParserInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailtrapDriver implements DriverInterface, HandlesWebhooks
{
    public function __construct(
        protected TranslatorInterface $translator,
        protected ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function availableSettings(): array
    {
        return [
            'mail_mailtrap_api_token' => '',
            'mail_mailtrap_mode' => [
                'sending' => $this->translator->trans('fof-mailtrap-mail.admin.mode.sending'),
                'sandbox' => $this->translator->trans('fof-mailtrap-mail.admin.mode.sandbox'),
            ],
            'mail_mailtrap_inbox_id' => '',
            'mail_mailtrap_category' => '',
            'mail_mailtrap_webhook_signing_secret' => '',
        ];
    }

    public function validate(SettingsRepositoryInterface $settings, Factory $validator): MessageBag
    {
        return $validator->make($settings->all(), [
            'mail_mailtrap_api_token' => ['required', 'regex:/^\S+$/'],
            'mail_mailtrap_mode' => ['required', 'in:sending,sandbox'],
            'mail_mailtrap_inbox_id' => ['nullable', 'required_if:mail_mailtrap_mode,sandbox', 'regex:/^\d+$/'],
            'mail_mailtrap_category' => ['nullable', 'string', 'max:255'],
            'mail_mailtrap_webhook_signing_secret' => ['nullable', 'regex:/^\S+$/'],
        ])->errors();
    }

    public function canSend(): bool
    {
        return true;
    }

    public function buildTransport(SettingsRepositoryInterface $settings): TransportInterface
    {
        $sandbox = $settings->get('mail_mailtrap_mode') === 'sandbox';

        $config = (new Config($settings->get('mail_mailtrap_api_token')))
            ->setHttpClient(new Psr18Client($this->httpClient ?? HttpClient::create()))
            ->setHost($sandbox ? AbstractApi::SENDMAIL_SANDBOX_HOST : AbstractApi::SENDMAIL_TRANSACTIONAL_HOST);

        $emails = $sandbox
            ? new SandboxEmails($config, (int) $settings->get('mail_mailtrap_inbox_id'))
            : new SendingEmails($config);

        return new MailtrapTransport($emails, $config, $settings->get('mail_mailtrap_category') ?: null);
    }

    public function getWebhookRequestParser(SettingsRepositoryInterface $settings): RequestParserInterface
    {
        return new MailtrapRequestParser(new MailtrapPayloadConverter());
    }

    public function getWebhookSigningSecret(SettingsRepositoryInterface $settings): ?string
    {
        return $settings->get('mail_mailtrap_webhook_signing_secret') ?: null;
    }

    public function webhookConfigured(SettingsRepositoryInterface $settings): bool
    {
        return $this->getWebhookSigningSecret($settings) !== null;
    }

    public function webhooksAvailable(SettingsRepositoryInterface $settings): bool
    {
        return $settings->get('mail_mailtrap_mode') !== 'sandbox';
    }

    public function isPermanentFailure(MailerDeliveryEvent $event): bool
    {
        return in_array($event->getPayload()['event'] ?? null, ['bounce', 'suspension', 'reject'], true);
    }
}
