import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';
import classList from 'flarum/common/utils/classList';
import LangDisplayName from '../util/LangDisplayName';

/**
 * "This post is in {language}" label, identical to ianm/translate's.
 */
export default class DetectedLanguageLabel extends Component {
  view() {
    const { detectedLang, context } = this.attrs;

    const translationKey = context === 'post' ? 'itqan-translate.forum.detected-language' : 'itqan-translate.forum.discussion.title.detected-language';

    const className = classList({
      PostMeta: true,
      'Translation--meta': true,
      [`PostMeta--${detectedLang}`]: true,
      [`Translation--${context}`]: true,
    });

    return (
      <div className={className}>
        <span>
          <Icon name="fas fa-quote-right" />
          {app.translator.trans(translationKey, {
            postLang: LangDisplayName(detectedLang),
          })}
        </span>
      </div>
    );
  }
}
