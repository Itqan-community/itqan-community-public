import Mithril, { Vnode } from 'mithril';
import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import humanTime from 'flarum/common/helpers/humanTime';

interface TranslatedPostFooterAttrs {
  updatedAt: Date;
  provider: string;
}

export default class TranslatedPostFooter extends Component<TranslatedPostFooterAttrs> {
  view(vnode: Vnode<TranslatedPostFooterAttrs>): Mithril.Children {
    const { updatedAt, provider } = vnode.attrs;

    return (
      <div className="Meta Translation--meta">
        {app.translator.trans('ianm-translate.forum.discussion.post.translated-updated', {
          time: humanTime(updatedAt),
          provider: app.translator.trans(`ianm-translate.lib.providers.${provider}`),
        })}
      </div>
    );
  }
}
