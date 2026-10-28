<?php

/*
 * This file is part of fof/mailtrap-mail.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MailtrapMail\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Flarum\Settings\OverrideSettingsRepository;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\MailtrapMail\PinnedSettings;
use Illuminate\Contracts\Container\Container;

/**
 * Pins the Mailtrap configuration in code, for deployments (CI, staging,
 * Docker images) where it belongs to the environment rather than to an
 * administrator.
 *
 * ```php
 * (new FoF\MailtrapMail\Extend\Mailtrap())
 *     ->force()
 *     ->apiToken(getenv('MAILTRAP_API_TOKEN') ?: null)
 *     ->mode('sandbox')
 *     ->inboxId(getenv('MAILTRAP_INBOX_ID') ?: null);
 * ```
 *
 * A pinned value takes precedence over the stored setting. A null or empty
 * value pins nothing, so the stored setting still applies.
 */
class Mailtrap implements ExtenderInterface
{
    /** @var array<string, string> */
    private array $pinned = [];

    /**
     * Use Mailtrap as the forum's mail driver, whatever the Email page says.
     */
    public function force(): static
    {
        return $this->pin('mail_driver', 'mailtrap');
    }

    public function apiToken(?string $token): static
    {
        return $this->pin('mail_mailtrap_api_token', $token);
    }

    /**
     * @param 'sending'|'sandbox'|null $mode
     */
    public function mode(?string $mode): static
    {
        return $this->pin('mail_mailtrap_mode', $mode);
    }

    /**
     * The sandbox inbox that receives mail in sandbox mode.
     */
    public function inboxId(int|string|null $inboxId): static
    {
        return $this->pin('mail_mailtrap_inbox_id', $inboxId);
    }

    public function category(?string $category): static
    {
        return $this->pin('mail_mailtrap_category', $category);
    }

    /**
     * The signing secret of the Mailtrap webhook that reports bounces and spam complaints.
     */
    public function webhookSigningSecret(?string $secret): static
    {
        return $this->pin('mail_mailtrap_webhook_signing_secret', $secret);
    }

    protected function pin(string $key, int|string|null $value): static
    {
        if ($value !== null && $value !== '') {
            $this->pinned[$key] = (string) $value;
        }

        return $this;
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        $pinned = $this->pinned;

        $container->singletonIf(PinnedSettings::class);
        $registry = $container->make(PinnedSettings::class);
        $registry->values = array_merge($registry->values, $pinned);

        $container->extend(
            SettingsRepositoryInterface::class,
            fn (SettingsRepositoryInterface $settings) => new OverrideSettingsRepository($settings, $pinned)
        );
    }
}
