import app from 'flarum/admin/app';
import { override } from 'flarum/common/extend';
import MailPage from 'flarum/admin/components/MailPage';

app.initializers.add('fof-mailtrap-mail', () => {
  // A setting pinned in code by the Mailtrap extender wins over the stored
  // value, so editing it here would have no effect. Show it locked instead.
  override(MailPage.prototype, 'buildSettingComponent', function (original, entry) {
    const pinned = (app.data['fof-mailtrap-mail.pinned'] as string[] | undefined) ?? [];

    if (typeof entry === 'function' || !pinned.includes(entry.setting)) {
      return original(entry);
    }

    return original({
      ...entry,
      disabled: true,
      help: app.translator.trans('fof-mailtrap-mail.admin.pinned_help'),
    });
  });
});
