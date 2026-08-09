import Post from 'flarum/common/models/Post';
import Discussion from 'flarum/common/models/Discussion';
import PostTranslation from '../../common/model/PostTranslation';
import DiscussionTranslation from '../../common/model/DiscussionTranslation';
export default class TranslationState {
    item: Post | Discussion;
    itemType: 'post' | 'discussion';
    constructor(item: Post | Discussion);
    showing: boolean;
    loading: boolean;
    language: string | null;
    currentTranslation: PostTranslation | DiscussionTranslation | undefined;
    closeTranslation(): void;
    constructUrl(code: string, refresh?: boolean): string;
    handleResponse(response: any, code: string): void;
    loadTranslation(code: string): void;
    refreshTranslation(translation: PostTranslation | DiscussionTranslation): void;
}
