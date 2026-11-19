import Modal, { IInternalModalAttrs } from 'flarum/common/components/Modal';
import app from 'flarum/forum/app';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';

export interface AskItqanModalAttrs extends IInternalModalAttrs {
  initialQuery?: string;
}

interface DiscussionResult {
  id: number;
  title: string;
  url: string;
  created_at: string;
  reply_count: number;
  snippet?: string;
  author?: {
    username?: string;
    display_name?: string;
  };
}

interface AskResponse {
  query: string;
  found: boolean;
  answer: string;
  discussions: DiscussionResult[];
  posts: any[];
}

export default class AskItqanModal extends Modal<AskItqanModalAttrs> {
  query: string = '';
  loading: boolean = false;
  data: AskResponse | null = null;
  error: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.query = this.attrs.initialQuery || '';

    if (this.query.trim()) {
      this.executeAsk(this.query);
    }
  }

  className() {
    return 'Modal--large AskItqanModal';
  }

  title() {
    return (
      <div className="AskItqanModal-titleWrapper">
        <i className="fas fa-sparkles AskItqanModal-titleIcon" aria-hidden="true" />
        <span>{app.translator.trans('itqan-community-mcp.forum.ask.modal_title')}</span>
      </div>
    );
  }

  content() {
    const chips = [
      app.translator.trans('itqan-community-mcp.forum.ask.chip_asr'),
      app.translator.trans('itqan-community-mcp.forum.ask.chip_uthmani'),
      app.translator.trans('itqan-community-mcp.forum.ask.chip_tagging'),
      app.translator.trans('itqan-community-mcp.forum.ask.chip_trusted'),
    ];

    return (
      <div className="Modal-body AskItqanModal-body">
        <p className="AskItqanModal-subtitle">
          {app.translator.trans('itqan-community-mcp.forum.ask.modal_subtitle')}
        </p>

        {/* Suggestion Chips */}
        <div className="AskItqanModal-chips">
          {chips.map((chipText) => (
            <button
              type="button"
              key={String(chipText)}
              className="AskItqanModal-chip"
              onclick={() => {
                this.query = String(chipText);
                this.executeAsk(this.query);
              }}
            >
              <i className="fas fa-search AskItqanModal-chipIcon" aria-hidden="true" />
              <span>{chipText}</span>
            </button>
          ))}
        </div>

        {/* Search Query Input */}
        <form className="AskItqanModal-searchForm" onsubmit={this.onsubmit.bind(this)}>
          <div className="AskItqanModal-inputGroup">
            <input
              type="text"
              className="FormControl AskItqanModal-input"
              placeholder={app.translator.trans('itqan-community-mcp.forum.ask.input_placeholder')}
              value={this.query}
              oninput={(e: any) => {
                this.query = e.target.value;
              }}
              disabled={this.loading}
            />
            <Button
              className="Button Button--primary AskItqanModal-submitBtn"
              type="submit"
              loading={this.loading}
              icon="fas fa-paper-plane"
            >
              {app.translator.trans('itqan-community-mcp.forum.ask.submit')}
            </Button>
          </div>
        </form>

        {/* Error message */}
        {this.error && <div className="Alert Alert--error AskItqanModal-alert">{this.error}</div>}

        {/* Loading state */}
        {this.loading && (
          <div className="AskItqanModal-loading">
            <LoadingIndicator />
            <p>{app.translator.trans('itqan-community-mcp.forum.ask.loading')}</p>
          </div>
        )}

        {/* Results / Answer */}
        {!this.loading && this.data && (
          <div className="AskItqanModal-results">
            {/* Grounded synthesis answer */}
            <div className="AskItqanModal-answerBox">
              <div className="AskItqanModal-answerHeader">
                <i className="fas fa-robot" aria-hidden="true" />
                <strong>إجابة مستخلصة من نقاشات المجتمع:</strong>
              </div>
              <div className="AskItqanModal-answerText">
                {this.data.answer.split('\n\n').map((para, idx) => (
                  <p key={idx}>{para}</p>
                ))}
              </div>
            </div>

            {/* Documented Discussion Cards */}
            {this.data.discussions && this.data.discussions.length > 0 && (
              <div className="AskItqanModal-sources">
                <h4 className="AskItqanModal-sourcesTitle">
                  {app.translator.trans('itqan-community-mcp.forum.ask.sources_title')}
                </h4>
                <div className="AskItqanModal-sourcesList">
                  {this.data.discussions.map((disc) => (
                    <div key={disc.id} className="AskItqanModal-sourceCard">
                      <div className="AskItqanModal-sourceCardHeader">
                        <a
                          href={disc.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="AskItqanModal-sourceTitle"
                        >
                          {disc.title}
                        </a>
                        <div className="AskItqanModal-sourceMeta">
                          {disc.author && (
                            <span className="AskItqanModal-sourceAuthor">
                              @{disc.author.display_name || disc.author.username}
                            </span>
                          )}
                          <span className="AskItqanModal-sourceDate">{disc.created_at}</span>
                          <span className="AskItqanModal-sourceReplies">
                            {app.translator.trans('itqan-community-mcp.forum.ask.replies_count', {
                              count: disc.reply_count,
                            })}
                          </span>
                        </div>
                      </div>
                      {disc.snippet && <p className="AskItqanModal-sourceSnippet">{disc.snippet}</p>}
                      <div className="AskItqanModal-sourceActions">
                        <a
                          href={disc.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="Button Button--link Button--sm"
                        >
                          <i className="fas fa-external-link-alt" aria-hidden="true" />{' '}
                          {app.translator.trans('itqan-community-mcp.forum.ask.open_discussion')}
                        </a>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Empty state or ask new topic action */}
            {(!this.data.found || (this.data.discussions && this.data.discussions.length === 0)) && (
              <div className="AskItqanModal-emptyAction">
                <p className="AskItqanModal-emptyText">
                  {app.translator.trans('itqan-community-mcp.forum.ask.empty_state')}
                </p>
                <p className="AskItqanModal-promptText">
                  {app.translator.trans('itqan-community-mcp.forum.ask.new_topic_prompt')}
                </p>
                <Button
                  className="Button Button--primary AskItqanModal-newTopicBtn"
                  icon="fas fa-pen"
                  onclick={() => this.startNewDiscussion()}
                >
                  {app.translator.trans('itqan-community-mcp.forum.ask.new_topic_button')}
                </Button>
              </div>
            )}
          </div>
        )}
      </div>
    );
  }

  onsubmit(e: any) {
    if (e) e.preventDefault();
    if (!this.query.trim() || this.loading) return;
    this.executeAsk(this.query);
  }

  executeAsk(queryText: string) {
    const trimmed = queryText.trim();
    if (!trimmed) return;

    this.loading = true;
    this.error = null;
    this.data = null;
    m.redraw();

    const apiUrl = app.forum.attribute('apiUrl') || '/api';

    app
      .request({
        method: 'GET',
        url: `${apiUrl}/itqan/ask?q=${encodeURIComponent(trimmed)}`,
      })
      .then((response: any) => {
        this.data = response as AskResponse;
        this.loading = false;
        m.redraw();
      })
      .catch((err: any) => {
        this.error = (err && err.message) || 'حدث خطأ أثناء استرجاع الإجابة. يرجى المحاولة مرة أخرى.';
        this.loading = false;
        m.redraw();
      });
  }

  startNewDiscussion() {
    this.hide();

    if (app.session.user) {
      app.composer
        .load(() => import('flarum/forum/components/DiscussionComposer'), { user: app.session.user })
        .then(() => {
          app.composer.show();
          if (this.query && (app.composer.fields as any)?.title) {
            (app.composer.fields as any).title(this.query);
          }
        });
    } else {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));
    }
  }
}
