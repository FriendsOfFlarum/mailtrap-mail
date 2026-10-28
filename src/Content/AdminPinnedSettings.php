<?php

/*
 * This file is part of fof/mailtrap-mail.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MailtrapMail\Content;

use Flarum\Frontend\Document;
use FoF\MailtrapMail\PinnedSettings;

/**
 * Tells the admin Email page which settings are pinned in code, so it can show
 * them as locked rather than offering edits that would be ignored.
 *
 * A pinned secret is masked: it usually comes from the deployment's
 * environment, and the page has no use for it once it cannot be edited.
 */
class AdminPinnedSettings
{
    public const SECRETS = ['mail_mailtrap_api_token', 'mail_mailtrap_webhook_signing_secret'];

    public const MASK = '••••••••';

    public function __construct(
        protected PinnedSettings $pinned,
    ) {
    }

    public function __invoke(Document $document): void
    {
        $document->payload['fof-mailtrap-mail.pinned'] = array_keys($this->pinned->values);

        foreach (self::SECRETS as $key) {
            if (isset($this->pinned->values[$key])) {
                $document->payload['settings'][$key] = self::MASK;
            }
        }
    }
}
