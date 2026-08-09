import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import DiscussionHero from 'flarum/forum/components/DiscussionHero';
import type Mithril from 'mithril';
import ItemList from 'flarum/common/utils/ItemList';
import DiscussionTitle from '../components/DiscussionTitle';

export default function extendDiscussions() {
  extend(DiscussionHero.prototype, 'items', function (items: ItemList<Mithril.Children>) {
    const discussion = this.attrs.discussion;
    const detectedLang = discussion.detectedLang?.();
    const availableLanguages = Object.values(app.forum.attribute('ianm-translate.supportedLanguages')).filter((code) => code !== detectedLang);

    items.setContent('title', <DiscussionTitle discussion={discussion} languages={availableLanguages} iconOnly={false} />);

    return items;
  });
}
