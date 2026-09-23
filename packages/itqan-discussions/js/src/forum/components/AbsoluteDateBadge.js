import Component from 'flarum/common/Component';
import humanTime from 'flarum/common/helpers/humanTime';
import dayjs from 'dayjs';
import localizedFormat from 'dayjs/plugin/localizedFormat';

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
      const formattedDate = d.format('LL');
      const isoTimestamp = d.toISOString();
      const fullTimeStr = d.format('LLLL');

      return (
        <time pubdate datetime={isoTimestamp} title={fullTimeStr} className="AbsoluteDateBadge">
          {formattedDate}
        </time>
      );
    }

    return humanTime(time);
  }
}
