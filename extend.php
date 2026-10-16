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

use Flarum\Extend;

return [
    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->content(Content\AdminPinnedSettings::class),

    (new Extend\Mail())
        ->driver('mailtrap', MailtrapDriver::class),

    (new Extend\Settings())
        ->default('mail_mailtrap_mode', 'sending'),
];
