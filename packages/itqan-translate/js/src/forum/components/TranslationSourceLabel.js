import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Icon from 'flarum/common/components/Icon';

export default class TranslationSourceLabel extends Component {
  view() {
    const { from, to } = this.attrs;

    return (
      <div className="Meta Translation--meta">
        <Icon name="fas fa-language" />
        {app.translator.trans('itqan-translate.forum.discussion.post.translated-header', { from, to })}
      </div>
    );
  }
}
