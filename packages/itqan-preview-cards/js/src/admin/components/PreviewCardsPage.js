import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import ColorPreviewInput from 'flarum/common/components/ColorPreviewInput';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import ImageUploader from './ImageUploader';

const PREFIX = 'itqan-preview-cards.';

const ELEMENT_TOGGLES = [
  'show_logo',
  'show_brand',
  'show_tagline',
  'show_tag',
  'show_excerpt',
  'show_author',
  'show_avatar',
  'show_date',
  'show_replies',
  'show_last_reply',
];

const COLORS = [
  ['color_background', 'background'],
  ['color_accent', 'accent'],
  ['color_title', 'title'],
  ['color_text', 'text'],
  ['color_meta', 'meta'],
  ['color_tag', 'tag'],
];

export default class PreviewCardsPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);

    this.clearing = false;
  }

  content() {
    return (
      <div className="PreviewCardsPage">
        {this.section(
          'itqan-preview-cards.admin.elements.heading',
          'itqan-preview-cards.admin.elements.help',
          <div>
            {ELEMENT_TOGGLES.map((key) => (
              <div className="Form-group" key={key}>
                <Switch
                  state={this.setting(PREFIX + key)() === '1'}
                  onchange={(value) => this.setting(PREFIX + key)(value ? '1' : '0')}
                >
                  {app.translator.trans(`itqan-preview-cards.admin.elements.${key.slice(5)}`)}
                </Switch>
              </div>
            ))}
          </div>
        )}

        {this.section(
          'itqan-preview-cards.admin.colors.heading',
          'itqan-preview-cards.admin.colors.help',
          <div>
            {COLORS.map(([key, label]) => (
              <div className="Form-group" key={key} style={{ maxWidth: '420px' }}>
                <label>{app.translator.trans(`itqan-preview-cards.admin.colors.${label}`)}</label>
                <ColorPreviewInput
                  value={this.colorValue(key)}
                  oninput={(e) => this.setting(PREFIX + key)(e.target.value)}
                />
              </div>
            ))}
          </div>
        )}

        {this.section(
          'itqan-preview-cards.admin.images.heading',
          'itqan-preview-cards.admin.images.help',
          <div>
            <div className="Form-group">
              <label>{app.translator.trans('itqan-preview-cards.admin.images.logo')}</label>
              <div className="Form-help">{app.translator.trans('itqan-preview-cards.admin.images.logo_help')}</div>
              <ImageUploader
                className="Button"
                route="itqan-preview-cards/logo"
                field="logo"
                settingKey={PREFIX + 'logo_path'}
                attribute="itqanPreviewCardsLogoUrl"
                uploadLabel={app.translator.trans('itqan-preview-cards.admin.images.upload_logo')}
                removeLabel={app.translator.trans('itqan-preview-cards.admin.images.remove_logo')}
              />
            </div>

            <div className="Form-group" style={{ marginTop: '25px' }}>
              <label>{app.translator.trans('itqan-preview-cards.admin.images.background')}</label>
              <div className="Form-help">
                {app.translator.trans('itqan-preview-cards.admin.images.background_help')}
              </div>
              <ImageUploader
                className="Button"
                route="itqan-preview-cards/background"
                field="background"
                settingKey={PREFIX + 'background_path'}
                attribute="itqanPreviewCardsBackgroundUrl"
                uploadLabel={app.translator.trans('itqan-preview-cards.admin.images.upload_background')}
                removeLabel={app.translator.trans('itqan-preview-cards.admin.images.remove_background')}
              />
            </div>
          </div>
        )}

        {this.section(
          'itqan-preview-cards.admin.branding.heading',
          null,
          <div>
            <div className="Form-group">
              <label>{app.translator.trans('itqan-preview-cards.admin.branding.brand_ar')}</label>
              <input type="text" className="FormControl" dir="rtl" bidi={this.setting(PREFIX + 'brand_ar')} />
            </div>
            <div className="Form-group">
              <label>{app.translator.trans('itqan-preview-cards.admin.branding.brand_en')}</label>
              <input type="text" className="FormControl" bidi={this.setting(PREFIX + 'brand_en')} />
            </div>
            <div className="Form-help">
              {app.translator.trans('itqan-preview-cards.admin.branding.brand_help')}
            </div>
            <div className="Form-group">
              <label>{app.translator.trans('itqan-preview-cards.admin.branding.tagline_ar')}</label>
              <input type="text" className="FormControl" dir="rtl" bidi={this.setting(PREFIX + 'tagline_ar')} />
            </div>
            <div className="Form-group">
              <label>{app.translator.trans('itqan-preview-cards.admin.branding.tagline_en')}</label>
              <input type="text" className="FormControl" bidi={this.setting(PREFIX + 'tagline_en')} />
            </div>
            <div className="Form-help">
              {app.translator.trans('itqan-preview-cards.admin.branding.tagline_help')}
            </div>
            <div className="Form-group">
              <label>{app.translator.trans('itqan-preview-cards.admin.branding.language')}</label>
              <Select
                className="FormControl"
                value={this.setting(PREFIX + 'language')() || 'auto'}
                options={{
                  auto: app.translator.trans('itqan-preview-cards.admin.branding.language_auto'),
                  ar: 'العربية',
                  en: 'English',
                }}
                onchange={(value) => this.setting(PREFIX + 'language')(value)}
              />
            </div>
          </div>
        )}

        {this.section(
          'itqan-preview-cards.admin.maintenance.heading',
          'itqan-preview-cards.admin.maintenance.clear_help',
          <Button
            className="Button"
            icon="fas fa-broom"
            loading={this.clearing}
            onclick={() => this.clearCache()}
          >
            {app.translator.trans('itqan-preview-cards.admin.maintenance.clear')}
          </Button>
        )}

        <div className="Form-group">{this.submitButton()}</div>
      </div>
    );
  }

  colorValue(key) {
    const value = this.setting(PREFIX + key)();

    if (value) {
      return value;
    }

    if (key === 'color_accent') {
      return app.data.settings['theme_primary_color'] || '#064e3b';
    }

    return '#ffffff';
  }

  section(heading, help, children) {
    return (
      <fieldset className="PreviewCardsPage-section" style={{ marginBottom: '30px' }}>
        <legend style={{ fontWeight: 'bold', marginBottom: '10px' }}>{app.translator.trans(heading)}</legend>
        {help ? <div className="Form-help" style={{ marginBottom: '15px' }}>{app.translator.trans(help)}</div> : null}
        {children}
      </fieldset>
    );
  }

  clearCache() {
    this.clearing = true;
    m.redraw();

    app
      .request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/itqan-preview-cards/clear-cache',
      })
      .then((response) => {
        this.clearing = false;

        app.alerts.show(
          { type: 'success' },
          app.translator.trans('itqan-preview-cards.admin.maintenance.cleared', {
            count: response.cleared,
            size: (response.freed / 1024).toFixed(1),
          })
        );

        m.redraw();
      })
      .catch(() => {
        this.clearing = false;
        m.redraw();
      });
  }
}
