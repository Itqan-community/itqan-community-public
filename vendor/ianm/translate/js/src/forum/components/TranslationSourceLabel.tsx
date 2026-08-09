import app from 'flarum/forum/app';
import Component, { ComponentAttrs } from 'flarum/common/Component';
import icon from 'flarum/common/helpers/icon';
import type Mithril from 'mithril';

interface ITranslationSourceLabelAttrs extends ComponentAttrs {
  from: string;
  to: string;
}

export default class TranslationSourceLabel extends Component<ITranslationSourceLabelAttrs> {
  view(vnode: Mithril.VnodeDOM<ITranslationSourceLabelAttrs>): Mithril.Children {
    const { from, to } = vnode.attrs;

    return (
      <div className={`Meta Translation--meta`}>
        {icon('fas fa-language')}
        {app.translator.trans('ianm-translate.forum.discussion.post.translated-header', { from, to })}
      </div>
    );
  }
}
