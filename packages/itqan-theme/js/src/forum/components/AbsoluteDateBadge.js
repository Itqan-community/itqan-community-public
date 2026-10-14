import Component from 'flarum/common/Component';
import app from 'flarum/forum/app';
import humanTime from 'flarum/common/helpers/humanTime';
import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';
import 'dayjs/locale/ar';

dayjs.extend(localizedFormat);

/**
 * The `AbsoluteDateBadge` component displays explicit calendar-date timestamps
 * (e.g. "July 16, 2026") for archival posts older than 30 days, while retaining
 * human-friendly relative timestamps for recent posts.
 */
export default class AbsoluteDateBadge extends Component {
  view() {
    const time = this.attrs.time;
    if (!time) return null;

    const d = dayjs(time);
    const now = dayjs();
    const diffDays = now.diff(d, 'day');

    // Posts older than 30 days display explicit calendar dates
    if (diffDays > 30) {
      // dayjs locale keys are short (e.g. 'ar', 'en'); a regional id like
      // 'ar-SA' would silently fall back to English. Strip the region.
      const locale = String(app.data.locale || 'en').split('-')[0];
      const formattedDate = d.locale(locale).format('LL');
      const isoTimestamp = d.toISOString();
      const fullTimeStr = d.locale(locale).format('LLLL');

      return (
        <time pubdate datetime={isoTimestamp} title={fullTimeStr} className="AbsoluteDateBadge">
          {formattedDate}
        </time>
      );
    }

    return humanTime(time);
  }
}
