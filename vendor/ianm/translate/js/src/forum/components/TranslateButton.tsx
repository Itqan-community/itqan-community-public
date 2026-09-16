import app from 'flarum/forum/app';
import Mithril from 'mithril';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import LangDisplayName from '../util/LangDisplayName';

interface ITranslateButtonAttrs extends ComponentAttrs {
  languages: string[];
  detectedLang: string;
  onTranslate: (code: string) => void;
  iconOnly?: boolean;
  loading?: boolean;
}

export default class TranslateButton extends Component<ITranslateButtonAttrs> {
  view(vnode: Mithril.Vnode<ITranslateButtonAttrs>) {
    const { languages, detectedLang, onTranslate, iconOnly, loading } = vnode.attrs;

    // Filter out the detected language from the list of languages
    const availableLanguages = languages.filter((code: string) => code !== detectedLang);

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
    } else {
      return (
        <Dropdown
          label={iconOnly ? null : app.translator.trans('ianm-translate.forum.translate-button-label')}
          buttonClassName="Button Button--link"
          icon={icon}
          disabled={loading}
        >
          {availableLanguages.map((code: string) => (
            <Button icon="fas fa-language" disabled={loading} onclick={() => onTranslate(code)}>
              {LangDisplayName(code)}
            </Button>
          ))}
        </Dropdown>
      );
    }
  }
}
