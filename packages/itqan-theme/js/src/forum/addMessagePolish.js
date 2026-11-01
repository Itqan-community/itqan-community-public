import app from 'flarum/forum/app';

/**
 * Adds the attributes the private-message layout needs but the API does not
 * provide:
 *
 *   data-own      the message was written by the reader
 *   data-grouped  the previous message has the same author
 *   data-day      first message of a day, holding the label to render
 *
 * They are attributes only — no nodes are inserted — so Mithril can re-render
 * the stream underneath us without the page drifting out of sync. A throttled
 * MutationObserver re-runs the pass whenever the stream changes (a message is
 * sent, a dialog is opened, an older page is loaded).
 */
export default function addMessagePolish() {
  let scheduled = false;

  const labelFor = (date) => {
    const locale = document.documentElement.lang || 'ar';
    const now = new Date();
    const day = new Date(date);
    const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();

    try {
      if (sameDay(day, now)) {
        return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(0, 'day');
      }

      const yesterday = new Date(now);
      yesterday.setDate(now.getDate() - 1);

      if (sameDay(day, yesterday)) {
        return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(-1, 'day');
      }

      return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(day);
    } catch (e) {
      return day.toLocaleDateString();
    }
  };

  const decorate = (stream) => {
    const me = app.session.user;

    if (!me || !stream) return;

    const mine = `/u/${me.id()}`;
    const items = stream.querySelectorAll('.MessageStream-item');
    let previousAuthor = null;
    let previousDay = null;

    items.forEach((item) => {
      const article = item.querySelector('.Message');

      // The reply placeholder lives in the same list but is not a message.
      if (!article) {
        previousAuthor = null;
        previousDay = null;
        return;
      }

      const author = item.querySelector('.PostUser-name a')?.getAttribute('href') || null;
      const time = item.querySelector('time[datetime]');

      if (author && author === mine) {
        item.setAttribute('data-own', '');
      } else {
        item.removeAttribute('data-own');
      }

      if (author && author === previousAuthor) {
        item.setAttribute('data-grouped', '');
      } else {
        item.removeAttribute('data-grouped');
      }

      if (time) {
        const date = new Date(time.getAttribute('datetime'));

        if (!Number.isNaN(date.getTime())) {
          const day = date.toDateString();

          if (day !== previousDay) {
            item.setAttribute('data-day', labelFor(date));
          } else {
            item.removeAttribute('data-day');
          }

          previousDay = day;
        }
      }

      previousAuthor = author;
    });
  };

  const run = () => {
    scheduled = false;

    document.querySelectorAll('.MessageStream').forEach((stream) => decorate(stream));
  };

  const schedule = () => {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(run);
  };

  const observer = new MutationObserver(schedule);

  const watch = () => {
    const content = document.getElementById('content') || document.body;
    observer.observe(content, { childList: true, subtree: true });
    schedule();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', watch);
  } else {
    watch();
  }
}
