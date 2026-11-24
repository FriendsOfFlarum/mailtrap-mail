<?php

/*
 * This file is part of fof/mailtrap-mail.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MailtrapMail\Listener;

use Flarum\Foundation\ValidationException;
use Flarum\Locale\TranslatorInterface;
use Flarum\Settings\Event\Deserializing;
use Flarum\Settings\Event\Saving;
use FoF\MailtrapMail\Content\AdminPinnedSettings;

class ProtectWebhookSecret
{
    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public function handle(Deserializing|Saving $event): void
    {
        $key = 'mail_mailtrap_webhook_signing_secret';

        if ($event instanceof Deserializing && ! empty($event->settings[$key])) {
            $event->settings[$key] = AdminPinnedSettings::MASK;
        } elseif ($event instanceof Saving && array_key_exists($key, $event->settings)) {
            if ($event->settings[$key] === AdminPinnedSettings::MASK) {
                unset($event->settings[$key]);

                return;
            }

            $value = $event->settings[$key];
            if (! is_string($value) || (trim($value) !== '' && ! preg_match('/\A[0-9a-fA-F]{32}\z/', trim($value)))) {
                throw new ValidationException([$key => $this->translator->trans('fof-mailtrap-mail.admin.invalid_webhook_secret')]);
            }

            $event->settings[$key] = trim($value);
        }
    }
}
