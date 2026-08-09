import Model from 'flarum/common/Model';
import Post from 'flarum/common/models/Post';
export default class PostTranslation extends Model {
    postId(): number;
    language(): string;
    translation(): string;
    provider(): string;
    translationHtml(): string;
    createdAt(): Date | null | undefined;
    updatedAt(): Date | null | undefined;
    post(): false | Post;
}
