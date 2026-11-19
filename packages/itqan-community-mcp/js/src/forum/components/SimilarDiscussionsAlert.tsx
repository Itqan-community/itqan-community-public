import Component, { ComponentAttrs } from 'flarum/common/Component';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';

export interface SimilarDiscussion {
  id: number;
  title: string;
  url: string;
  created_at: string;
  reply_count: number;
  author: string;
}

export interface SimilarDiscussionsAlertAttrs extends ComponentAttrs {
  matches: SimilarDiscussion[];
  onDismiss: () => void;
}

export default class SimilarDiscussionsAlert extends Component<SimilarDiscussionsAlertAttrs> {
  view() {
    const { matches, onDismiss } = this.attrs;

    if (!matches || matches.length === 0) {
      return null;
    }

    return (
      <div className="ItqanSimilarDiscussionsAlert">
        <div className="ItqanSimilarDiscussionsAlert-header">
          <div className="ItqanSimilarDiscussionsAlert-title">
            <i className="fas fa-lightbulb ItqanSimilarDiscussionsAlert-icon" aria-hidden="true" />
            <strong>{app.translator.trans('itqan-community-mcp.forum.composer.similar_title')}</strong>
          </div>
          <Button
            className="Button Button--link Button--icon ItqanSimilarDiscussionsAlert-dismiss"
            icon="fas fa-times"
            aria-label={app.translator.trans('itqan-community-mcp.forum.composer.dismiss')}
            onclick={onDismiss}
          />
        </div>
        <p className="ItqanSimilarDiscussionsAlert-subtitle">
          {app.translator.trans('itqan-community-mcp.forum.composer.similar_subtitle')}
        </p>
        <ul className="ItqanSimilarDiscussionsAlert-list">
          {matches.map((item) => (
            <li key={item.id} className="ItqanSimilarDiscussionsAlert-item">
              <a
                href={item.url || app.route.discussion({ id: item.id })}
                target="_blank"
                rel="noopener noreferrer"
                className="ItqanSimilarDiscussionsAlert-link"
              >
                <span className="ItqanSimilarDiscussionsAlert-itemTitle">{item.title}</span>
                <span className="ItqanSimilarDiscussionsAlert-itemMeta">
                  {item.author && <span className="ItqanSimilarDiscussionsAlert-author">@{item.author}</span>}
                  {item.created_at && <span className="ItqanSimilarDiscussionsAlert-date">{item.created_at}</span>}
                  <span className="ItqanSimilarDiscussionsAlert-replies">
                    {app.translator.trans('itqan-community-mcp.forum.composer.replies', { count: item.reply_count })}
                  </span>
                </span>
              </a>
            </li>
          ))}
        </ul>
      </div>
    );
  }
}
