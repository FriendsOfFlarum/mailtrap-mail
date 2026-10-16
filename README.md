# Mailtrap Mail by FriendsOfFlarum

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/mailtrap-mail.svg)](https://packagist.org/packages/fof/mailtrap-mail) [![OpenCollective](https://img.shields.io/badge/opencollective-fof-blue.svg)](https://opencollective.com/fof/donate)

A [Flarum](https://flarum.org) extension. Send your forum's email through the [Mailtrap](https://mailtrap.io) email API, or catch every email in a Mailtrap sandbox inbox while you test.

It uses Mailtrap's official PHP SDK and adds a `mailtrap` driver to your forum's **Email** settings.

### Features

- **Sending** through Mailtrap's transactional Email API, the right stream for forum mail such as notifications, password resets and email confirmations.
- **Sandbox mode** for staging and development. Every email lands in a Mailtrap test inbox and nothing reaches real members.
- **Categories.** Tag every email with a category so you can filter by it in Mailtrap's email logs and stats.
- **Clear errors.** When Mailtrap rejects an email (for example a revoked token or an unverified sending domain), Mailtrap's reason is logged and shown when you use **Send Test Mail**.

### Setup

1. In Mailtrap, add and verify your sending domain under **Sending Domains**. You can skip this for sandbox mode.
2. Create an API token under **Settings → API Tokens**. Give it access to your sending domain, or to your sandbox inbox when testing.
3. In your forum's admin panel, open **Email**, choose `mailtrap` as the driver, and paste in the token.
4. Pick a **Mode**:
   - **Send email** (the default) delivers to your members.
   - **Sandbox (testing)** catches mail in a test inbox. Enter the inbox ID, which is the number in the inbox's address, e.g. `1234567` in `mailtrap.io/inboxes/1234567/messages`.
5. Optionally, set a **Category**.
6. Make sure the **From** address belongs to your verified sending domain, save, and use **Send Test Mail** to check everything works.

### Using a sandbox on staging

Install the extension on your staging forum and choose **Sandbox (testing)** mode. Members' notifications, password resets and digests are all delivered into your Mailtrap inbox, so you can test real forum activity without emailing anyone. On production, use **Send email** mode.

### Configuring in code

Everything on the Email page can instead be pinned in your forum's `extend.php`. This suits CI, staging and Docker deployments, where the Mailtrap configuration belongs to the environment rather than to an administrator. Pinned values take precedence over the stored settings, and the Email page shows them locked.

```php
use FoF\MailtrapMail\Extend\Mailtrap;

return [
    (new Mailtrap())
        ->force()
        ->apiToken(getenv('MAILTRAP_API_TOKEN') ?: null)
        ->mode('sandbox')
        ->inboxId(getenv('MAILTRAP_INBOX_ID') ?: null)
        ->category('CI'),
];
```

| Method | Pins |
|---|---|
| `force()` | Mailtrap as the forum's mail driver. |
| `apiToken(?string $token)` | The API token. It is never sent to the admin page. |
| `mode(?string $mode)` | `'sending'` or `'sandbox'`. |
| `inboxId(int\|string\|null $inboxId)` | The sandbox inbox. |
| `category(?string $category)` | The category sent with every email. |

A null or empty value pins nothing, so the stored setting still applies. That way `getenv('…') ?: null` falls back to the Email page when the variable isn't set.

### Already sending through Mailtrap over SMTP?

We recommend switching to the API. It sends each email in a single HTTPS request instead of an SMTP conversation, works on hosts that block outgoing SMTP ports, and reports failures with Mailtrap's own error messages.

To switch, install this extension and create an API token as described above. Then, on the **Email** page, change the driver from `smtp` to `mailtrap` and paste in the token. Your sending domain in Mailtrap stays as it is.

### Installation

```sh
composer require fof/mailtrap-mail:"*"
```

### Updating

```sh
composer update fof/mailtrap-mail:"*"
php flarum cache:clear
```

### Links

[![OpenCollective](https://img.shields.io/badge/donate-friendsofflarum-44AEE5?style=for-the-badge&logo=open-collective)](https://opencollective.com/fof/donate)

- [Packagist](https://packagist.org/packages/fof/mailtrap-mail)
- [GitHub](https://github.com/FriendsOfFlarum/mailtrap-mail)
- [Discuss](https://discuss.flarum.org/d/40097)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum).
