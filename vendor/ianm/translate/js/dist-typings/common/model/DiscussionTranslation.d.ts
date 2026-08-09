import Model from 'flarum/common/Model';
import Discussion from 'flarum/common/models/Discussion';
export default class DiscussionTranslation extends Model {
    discussionId(): number;
    language(): string;
    translation(): string;
    provider(): string;
    createdAt(): Date | null | undefined;
    updatedAt(): Date | null | undefined;
    discussion(): false | Discussion;
}
