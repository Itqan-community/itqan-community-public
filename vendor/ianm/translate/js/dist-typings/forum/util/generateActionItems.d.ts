import ItemList from 'flarum/common/utils/ItemList';
import TranslationState from '../states/TranslationState';
import type Mithril from 'mithril';
import Post from 'flarum/common/models/Post';
import Discussion from 'flarum/common/models/Discussion';
import DiscussionTranslation from 'src/common/model/DiscussionTranslation';
import PostTranslation from 'src/common/model/PostTranslation';
export declare function generateActionItems(item: Post | Discussion, translation: DiscussionTranslation | PostTranslation, state: TranslationState): ItemList<Mithril.Children>;
