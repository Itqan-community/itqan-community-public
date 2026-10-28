import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import humanTime from 'flarum/common/helpers/humanTime';

export default class TranslatedPostFooter extends Component {
  view() {
    const { updatedAt, provider } = this.attrs;

    return (
      <div className="Meta Translation--meta">
        {app.translator.trans('itqan-translate.forum.discussion.post.translated-updated', {
          time: humanTime(updatedAt),
          provider: app.translator.trans(`itqan-translate.lib.providers.${provider}`),
        })}
      </div>
    );
  }
}
