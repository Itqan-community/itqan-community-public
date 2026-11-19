import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import askItqanState from '../askItqanState';
import itqanLogo from '../itqanLogo';

interface DiscussionResult {
  id: number;
  title: string;
  url: string;
  created_at: string;
  reply_count: number;
  snippet?: string;
  author?: { username?: string; display_name?: string };
}

interface AskResponse {
  query: string;
  found: boolean;
  answer?: string;
  summary?: string;
  provider?: string;
  discussions: DiscussionResult[];
}

type Message =
  | { role: 'user'; text: string }
  | { role: 'bot'; kind: 'welcome' }
  | { role: 'bot'; kind: 'loading' }
  | { role: 'bot'; kind: 'error'; text: string }
  | { role: 'bot'; kind: 'answer'; query: string; data: AskResponse };

const PROPOSAL_URL = 'https://community.itqan.dev/d/947';
const SUGGESTIONS = ['الرسم العثماني', 'التعرف الآلي على التلاوة ASR', 'معايير وسم الملفات الصوتية', 'موثوقية تطبيقات القرآن'];

function arabicAgo(dateStr: string): string {
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  const days = Math.floor((Date.now() - d.getTime()) / 86400000);
  if (days <= 0) return 'اليوم';
  if (days === 1) return 'منذ يوم';
  if (days === 2) return 'منذ يومين';
  if (days <= 10) return `منذ ${days} أيام`;
  if (days < 30) return `منذ ${days} يومًا`;
  const months = Math.floor(days / 30);
  if (months === 1) return 'منذ شهر';
  if (months < 12) return `منذ ${months} أشهر`;
  return `منذ ${Math.floor(months / 12)} سنة`;
}

function repliesLabel(n: number): string {
  if (!n) return 'بلا ردود';
  if (n === 1) return 'رد واحد';
  if (n === 2) return 'ردّان';
  if (n <= 10) return `${n} ردود`;
  return `${n} ردًا`;
}

/**
 * Floating "اسأل إتقان" launcher (bottom-right) and its chat panel.
 */
export default class FloatingAskItqan extends Component {
  messages: Message[] = [{ role: 'bot', kind: 'welcome' }];
  input = '';
  busy = false;
  bodyEl: HTMLElement | null = null;

  view() {
    const open = askItqanState.open;

    return (
      <div className={'ItqanChat' + (open ? ' is-open' : '')} dir="rtl">
        {open && this.panel()}
        <button
          type="button"
          className="ItqanFab"
          aria-label="اسأل إتقان"
          aria-expanded={open ? 'true' : 'false'}
          onclick={() => askItqanState.toggle()}
        >
          <span className="ItqanFab-logo">
            <img src={itqanLogo} alt="" />
          </span>
          <span className="ItqanFab-label">اسأل إتقان</span>
        </button>
      </div>
    );
  }

  panel() {
    return (
      <div className="ItqanChat-panel" role="dialog" aria-label="اسأل إتقان">
        <div className="ItqanChat-header">
          <img className="ItqanChat-headerLogo" src={itqanLogo} alt="إتقان" />
          <div className="ItqanChat-headerText">
            <div className="ItqanChat-title">اسأل إتقان</div>
            <div className="ItqanChat-subtitle">يجيب من نقاشات وتجارب المجتمع الموثقة</div>
          </div>
          <button type="button" className="ItqanChat-close" aria-label="إغلاق" onclick={() => askItqanState.hide()}>
            <i className="fas fa-times" aria-hidden="true" />
          </button>
        </div>

        <div
          className="ItqanChat-body"
          oncreate={(v: any) => {
            this.bodyEl = v.dom;
            this.scrollDown();
          }}
          onupdate={() => this.scrollDown()}
        >
          {this.messages.map((msg, i) => this.renderMessage(msg, i))}
        </div>

        <form className="ItqanChat-footer" onsubmit={(e: Event) => this.submit(e)}>
          <input
            className="ItqanChat-input"
            type="text"
            placeholder="جرّب: أبحث عن مصمم واجهات"
            value={this.input}
            oninput={(e: any) => (this.input = e.target.value)}
            oncreate={(v: any) => v.dom.focus()}
          />
          <button type="submit" className="ItqanChat-send" aria-label="إرسال" disabled={this.busy || !this.input.trim()}>
            <i className="fas fa-paper-plane" aria-hidden="true" />
          </button>
        </form>
      </div>
    );
  }

  renderMessage(msg: Message, key: number) {
    if (msg.role === 'user') {
      return (
        <div key={key} className="ItqanChat-row ItqanChat-row--user">
          <div className="ItqanChat-bubble ItqanChat-bubble--user">{msg.text}</div>
        </div>
      );
    }

    let content: any;
    switch (msg.kind) {
      case 'welcome':
        content = [
          <p className="ItqanChat-lead">السلام عليكم 👋 اسألني عن أي موضوع، وسأبحث لك في نقاشات المجتمع السابقة مع الروابط والتواريخ.</p>,
          <div className="ItqanChat-suggestions">
            {SUGGESTIONS.map((s) => (
              <button type="button" className="ItqanChat-chip" onclick={() => this.ask(s)}>
                {s}
              </button>
            ))}
          </div>,
        ];
        break;
      case 'loading':
        content = (
          <span className="ItqanChat-typing" aria-label="جارٍ البحث">
            <i />
            <i />
            <i />
          </span>
        );
        break;
      case 'error':
        content = <p className="ItqanChat-lead">{msg.text}</p>;
        break;
      case 'answer': {
        const list = (msg.data && msg.data.discussions) || [];
        if (!msg.data.found || list.length === 0) {
          content = [
            <p className="ItqanChat-lead">لم أجد نقاشًا سابقًا حول «{msg.query}».</p>,
            <p className="ItqanChat-muted">هل تودّ أن تكون أول من يطرحه ليجيبك أعضاء المجتمع؟</p>,
            <button type="button" className="ItqanChat-cta" onclick={() => this.startDiscussion(msg.query)}>
              <i className="fas fa-pen" aria-hidden="true" /> ابدأ نقاشًا جديدًا
            </button>,
          ];
        } else {
          const summaryText = msg.data.summary || '';
          content = [
            summaryText ? (
              <div className="ItqanChat-summary">
                <div className="ItqanChat-summaryBadge">
                  <i className="fas fa-sparkles" aria-hidden="true" />
                  <span>ملخص من تجارب المجتمع</span>
                </div>
                <div className="ItqanChat-summaryText">
                  {summaryText.split('\n\n').map((paragraph) => (
                    <p>{paragraph}</p>
                  ))}
                </div>
              </div>
            ) : null,
            <div className="ItqanChat-sourcesHeading">
              <i className="fas fa-comments" aria-hidden="true" />
              <span>المواضيع والمصادر ذات الصلة:</span>
            </div>,
            list.slice(0, 5).map((d) => (
              <a className="ItqanChat-card" href={d.url} target="_blank" rel="noopener noreferrer">
                <span className="ItqanChat-cardTitle">{d.title}</span>
                {d.snippet && <span className="ItqanChat-cardSnippet">"{d.snippet.slice(0, 110)}..."</span>}
                <span className="ItqanChat-cardMeta">
                  {(d.author && (d.author.display_name || d.author.username)) || ''}
                  {d.author ? ' · ' : ''}
                  {arabicAgo(d.created_at)} · {repliesLabel(d.reply_count)}
                </span>
              </a>
            )),
            <div className="ItqanChat-footerPrompt">
              <span className="ItqanChat-muted">لم تجد إجابة وافية؟</span>{' '}
              <button type="button" className="ItqanChat-link" onclick={() => this.startDiscussion(msg.query)}>
                اطرح سؤالك في موضوع جديد ←
              </button>
            </div>,
          ];
        }
        break;
      }
    }

    return (
      <div key={key} className="ItqanChat-row ItqanChat-row--bot">
        <div className="ItqanChat-bubble ItqanChat-bubble--bot">{content}</div>
      </div>
    );
  }

  scrollDown() {
    if (this.bodyEl) this.bodyEl.scrollTop = this.bodyEl.scrollHeight;
  }

  submit(e: Event) {
    e.preventDefault();
    this.ask(this.input);
  }

  ask(text: string) {
    const q = (text || '').trim();
    if (!q || this.busy) return;

    this.input = '';
    this.busy = true;
    this.messages.push({ role: 'user', text: q });
    const loading: Message = { role: 'bot', kind: 'loading' };
    this.messages.push(loading);
    m.redraw();

    const apiUrl = app.forum.attribute('apiUrl') || '/api';
    const replaceLoading = (next: Message) => {
      const idx = this.messages.indexOf(loading);
      if (idx >= 0) this.messages[idx] = next;
      this.busy = false;
      m.redraw();
    };

    app
      .request<AskResponse>({ method: 'GET', url: `${apiUrl}/itqan/ask?q=${encodeURIComponent(q)}` })
      .then((data: any) => replaceLoading({ role: 'bot', kind: 'answer', query: q, data }))
      .catch(() => replaceLoading({ role: 'bot', kind: 'error', text: 'تعذّر البحث الآن، حاول مرة أخرى بعد قليل.' }));
  }

  startDiscussion(title: string) {
    askItqanState.hide();

    if (!app.session.user) {
      app.modal.show(() => import('flarum/forum/components/LogInModal'));
      return;
    }

    app.composer
      .load(() => import('flarum/forum/components/DiscussionComposer'), { user: app.session.user })
      .then(() => {
        app.composer.show();
        const fields: any = app.composer.fields;
        if (fields && fields.title) fields.title(title);
      });
  }
}
