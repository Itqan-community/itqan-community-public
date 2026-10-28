import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';

/**
 * TEMPORARY diagnostic build: the full page is disabled while we find why the
 * route renders nothing on staging.
 */
export default class PrettyMailPage extends ExtensionPage {
  content() {
    return (
      <div className="PrettyMailPage">
        <div className="Form-group">
          <h3>DIAGNOSTIC: page reached</h3>
          <div className="helpText">{String(app.translator.trans('itqan-pretty-mail.admin.settings.heading'))}</div>
        </div>
      </div>
    );
  }
}
