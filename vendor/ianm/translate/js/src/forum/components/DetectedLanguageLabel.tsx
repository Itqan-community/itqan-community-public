import Mithril, { Vnode } from 'mithril';
import Component from 'flarum/common/Component';
import icon from 'flarum/common/helpers/icon';
import app from 'flarum/forum/app';
import LangDisplayName from '../util/LangDisplayName';
import classList from 'flarum/common/utils/classList';

export interface IDetectedLanguageLabelAttrs {
  detectedLang: string;
  context: 'post' | 'discussion';
}

export default class DetectedLanguageLabel extends Component<IDetectedLanguageLabelAttrs> {
  view(vnode: Vnode<IDetectedLanguageLabelAttrs>): Mithril.Children {
    const { detectedLang, context } = vnode.attrs;

    // Determine the correct translation key based on context
    const translationKey = context === 'post' ? 'ianm-translate.forum.detected-language' : 'ianm-translate.forum.discussion.title.detected-language';

    const className = classList({
      PostMeta: true,
      'Translation--meta': true,
      [`PostMeta--${detectedLang}`]: true,
      [`Translation--${context}`]: true,
    });

    return (
      <div className={className}>
        <span>
          {icon('fas fa-quote-right')}
          {app.translator.trans(translationKey, {
            postLang: LangDisplayName(detectedLang),
          })}
        </span>
      </div>
    );
  }
}
