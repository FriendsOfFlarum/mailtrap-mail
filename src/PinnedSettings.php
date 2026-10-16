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

/**
 * The settings pinned in code by {@see Extend\Mailtrap}, collected across
 * every instance of the extender.
 */
class PinnedSettings
{
    /** @var array<string, string> */
    public array $values = [];
}
