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
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\MessageBag;
use Mailtrap\Api\AbstractApi;
use Mailtrap\Api\Sandbox\Emails as SandboxEmails;
use Mailtrap\Api\Sending\Emails as SendingEmails;
use Mailtrap\Config;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MailtrapDriver implements DriverInterface
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
        ];
    }

    public function validate(SettingsRepositoryInterface $settings, Factory $validator): MessageBag
    {
        return $validator->make($settings->all(), [
            'mail_mailtrap_api_token' => ['required', 'regex:/^\S+$/'],
            'mail_mailtrap_mode' => ['required', 'in:sending,sandbox'],
            'mail_mailtrap_inbox_id' => ['nullable', 'required_if:mail_mailtrap_mode,sandbox', 'regex:/^\d+$/'],
            'mail_mailtrap_category' => ['nullable', 'string', 'max:255'],
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
}
