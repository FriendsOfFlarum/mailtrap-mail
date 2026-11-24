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

use Mailtrap\Api\EmailsSendApiInterface;
use Mailtrap\Bridge\Transport\MailtrapSdkTransport;
use Mailtrap\Config;
use Mailtrap\EmailHeader\CategoryHeader;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Message;

class MailtrapTransport extends MailtrapSdkTransport
{
    public function __construct(
        EmailsSendApiInterface $emails,
        Config $config,
        protected ?string $category = null,
    ) {
        parent::__construct($emails, $config);
    }

    protected function doSend(SentMessage $message): void
    {
        $this->applyCategory($message);

        // The SDK reports a failed send as a plain mailer RuntimeException.
        // Symfony's TransportInterface promises a TransportExceptionInterface,
        // which is what mailer consumers catch, so rethrow it as one.
        try {
            parent::doSend($message);
        } catch (RuntimeException $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Mailtrap accepts one category per email, so one already set on the
     * message (e.g. through MailtrapEmail::category()) wins.
     *
     * TODO: Tag notification emails by their blueprint type.
     */
    protected function applyCategory(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (! $this->category || ! $original instanceof Message || $original->getHeaders()->has(CategoryHeader::VAR_NAME)) {
            return;
        }

        $original->getHeaders()->add(new CategoryHeader($this->category));
    }
}
