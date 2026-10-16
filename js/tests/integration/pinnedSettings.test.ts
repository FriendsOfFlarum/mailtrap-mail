import bootstrapAdmin from '@flarum/jest-config/src/bootstrap/admin';
import app from 'flarum/admin/app';
import MailPage from 'flarum/admin/components/MailPage';
import m from 'mithril';
import mq from 'mithril-query';
import '../../src/admin';

beforeAll(() => {
  bootstrapAdmin();
  app.boot();
});

/**
 * Render the Mailtrap fields of core's Email page, with the given settings
 * pinned in code by the extender.
 */
function mailPage(pinned: string[]) {
  app.data['fof-mailtrap-mail.pinned'] = pinned;
  app.data.settings.mail_driver = 'mailtrap';

  const page = Object.create(MailPage.prototype) as MailPage;

  page.settings = {};
  page.status = { sending: true, errors: {} };
  page.driverFields = {
    mailtrap: {
      mail_mailtrap_api_token: '',
      mail_mailtrap_mode: { sending: 'Send email', sandbox: 'Sandbox (testing)' },
      mail_mailtrap_inbox_id: '',
    },
  };

  return mq(m('div', page.mailSettingItems().toArray()));
}

describe('settings pinned in code', () => {
  it('cannot be edited on the Email page', () => {
    const page = mailPage(['mail_driver', 'mail_mailtrap_mode']);

    expect(page.find('select[disabled]')).toHaveLength(2);
  });

  it('say they are set in code', () => {
    const page = mailPage(['mail_mailtrap_mode']);

    expect(page).toContainRaw('fof-mailtrap-mail.admin.pinned_help');
  });

  it('leave the other settings editable', () => {
    const page = mailPage(['mail_mailtrap_mode']);

    expect(page.find('input[disabled]')).toHaveLength(0);
    expect(page.find('select[disabled]')).toHaveLength(1);
  });
});
