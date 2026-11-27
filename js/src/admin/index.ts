import app from 'flarum/admin/app';
import { extend, override } from 'flarum/common/extend';
import MailPage from 'flarum/admin/components/MailPage';

const webhookSecret = 'mail_mailtrap_webhook_signing_secret';
const secretMask = '••••••••';

app.initializers.add('fof-mailtrap-mail', () => {
  // A setting pinned in code by the Mailtrap extender wins over the stored
  // value, so editing it here would have no effect. Show it locked instead.
  override(MailPage.prototype, 'buildSettingComponent', function (original, entry) {
    const pinned = (app.data['fof-mailtrap-mail.pinned'] as string[] | undefined) ?? [];

    if (typeof entry === 'function') return original(entry);

    if (entry.setting === webhookSecret) {
      entry = { ...entry, type: 'password', autocomplete: 'new-password' };
    }

    if (!pinned.includes(entry.setting)) return original(entry);

    return original({
      ...entry,
      disabled: true,
      help: app.translator.trans('fof-mailtrap-mail.admin.pinned_help'),
    });
  });

  extend(MailPage.prototype, 'onsaved', function () {
    if (app.data.settings[webhookSecret]) {
      app.data.settings[webhookSecret] = secretMask;
      this.settings[webhookSecret]?.(secretMask);
    }
  });
});
