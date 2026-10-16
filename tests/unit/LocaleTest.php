<?php

/*
 * This file is part of fof/mailtrap-mail.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\MailtrapMail\Tests\unit;

use Flarum\Testing\unit\TestCase;
use FoF\MailtrapMail\MailtrapDriver;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Yaml\Yaml;

class LocaleTest extends TestCase
{
    /**
     * Core's Mail settings page builds the driver's heading, description and
     * field labels from the core.admin.email namespace.
     */
    private function mailPageStrings(): array
    {
        return Yaml::parseFile(__DIR__.'/../../locale/en.yml')['core']['admin']['email'] ?? [];
    }

    #[Test]
    public function the_mail_page_has_a_heading_and_description_for_the_driver(): void
    {
        $strings = $this->mailPageStrings();

        $this->assertNotEmpty($strings['mailtrap_heading'] ?? null);
        $this->assertNotEmpty($strings['mailtrap_description'] ?? null);
    }

    #[Test]
    public function every_driver_field_has_a_label_and_help_text(): void
    {
        $strings = $this->mailPageStrings();

        foreach (array_keys((new MailtrapDriver(new IdentityTranslator()))->availableSettings()) as $field) {
            $this->assertNotEmpty($strings["{$field}_label"] ?? null, "Missing label for $field");
            $this->assertNotEmpty($strings["{$field}_help"] ?? null, "Missing help for $field");
        }
    }

    #[Test]
    public function a_setting_pinned_in_code_explains_why_it_is_locked(): void
    {
        $strings = Yaml::parseFile(__DIR__.'/../../locale/en.yml')['fof-mailtrap-mail']['admin'] ?? [];

        $this->assertNotEmpty($strings['pinned_help'] ?? null);
    }
}
