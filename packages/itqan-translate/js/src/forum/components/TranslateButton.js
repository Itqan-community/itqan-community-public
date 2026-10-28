import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import LangDisplayName from '../util/LangDisplayName';

/**
 * `fas fa-language` translate control.
 *
 * Same two shapes as `ianm/translate`: a plain button when one language is
 * configured, a dropdown when several are, so the user can always pick either
 * language even when detection is wrong.
 */
export default class TranslateButton extends Component {
  view() {
    const { languages = [], onTranslate, iconOnly, loading } = this.attrs;
    const availableLanguages = languages;

    if (availableLanguages.length === 0) {
      return null;
    }

    const icon = loading ? 'fas fa-spinner fa-spin' : 'fas fa-language';

    if (availableLanguages.length === 1) {
      const code = availableLanguages[0];

      return (
        <Button className="Button Button--link" icon={icon} disabled={loading} onclick={() => onTranslate(code)}>
          {iconOnly ? null : LangDisplayName(code)}
        </Button>
      );
    }

    return (
      <Dropdown
        label={iconOnly ? null : app.translator.trans('itqan-translate.forum.translate-button-label')}
        buttonClassName="Button Button--link"
        icon={icon}
        disabled={loading}
      >
        {availableLanguages.map((code) => (
          <Button icon="fas fa-language" disabled={loading} onclick={() => onTranslate(code)}>
            {LangDisplayName(code)}
          </Button>
        ))}
      </Dropdown>
    );
  }
}
