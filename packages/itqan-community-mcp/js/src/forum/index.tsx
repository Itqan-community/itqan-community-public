import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Button from 'flarum/common/components/Button';
import AskItqanModal from './components/AskItqanModal';
import SimilarDiscussionsAlert, { SimilarDiscussion } from './components/SimilarDiscussionsAlert';

export { default as AskItqanModal } from './components/AskItqanModal';
export { default as SimilarDiscussionsAlert } from './components/SimilarDiscussionsAlert';

app.initializers.add('itqan-community-mcp', () => {
  // 1. Add "إسأل إتقان" button to forum header navigation
  extend('flarum/forum/components/HeaderSecondary', 'items', function (items) {
    items.add(
      'itqanAskButton',
      <Button
        className="Button Button--askItqan"
        icon="fas fa-sparkles"
        title={app.translator.trans('itqan-community-mcp.forum.ask.tooltip')}
        onclick={() => app.modal.show(AskItqanModal, {})}
      >
        <span>{app.translator.trans('itqan-community-mcp.forum.ask.button')}</span>
      </Button>,
      25
    );
  });

  // 2. Add "هل اطلعت على هذا؟" prior-art duplicate check to DiscussionComposer
  extend('flarum/forum/components/DiscussionComposer', 'oninit', function () {
    (this as any).itqanSimilarMatches = [] as SimilarDiscussion[];
    (this as any).itqanSimilarDismissed = false;
    (this as any).itqanLastCheckedTitle = '';
    (this as any).itqanSimilarTimer = null as any;
  });

  extend('flarum/forum/components/DiscussionComposer', 'headerItems', function (items) {
    const titleVal = typeof (this as any).title === 'function' ? (this as any).title() : '';
    const composer = this as any;

    if (titleVal !== composer.itqanLastCheckedTitle) {
      composer.itqanLastCheckedTitle = titleVal;

      if (composer.itqanSimilarTimer) {
        clearTimeout(composer.itqanSimilarTimer);
      }

      const trimmed = (titleVal || '').trim();

      if (trimmed.length >= 4 && !composer.itqanSimilarDismissed) {
        composer.itqanSimilarTimer = setTimeout(() => {
          const apiUrl = app.forum.attribute('apiUrl') || '/api';
          app
            .request({
              method: 'GET',
              url: `${apiUrl}/itqan/similar?title=${encodeURIComponent(trimmed)}`,
            })
            .then((res: any) => {
              composer.itqanSimilarMatches = (res && res.matches) || [];
              m.redraw();
            })
            .catch(() => {
              // Silently ignore background check failures
            });
        }, 400);
      } else if (trimmed.length < 4) {
        composer.itqanSimilarMatches = [];
      }
    }

    if (
      !composer.itqanSimilarDismissed &&
      composer.itqanSimilarMatches &&
      composer.itqanSimilarMatches.length > 0
    ) {
      items.add(
        'itqanSimilarAlert',
        <SimilarDiscussionsAlert
          matches={composer.itqanSimilarMatches}
          onDismiss={() => {
            composer.itqanSimilarDismissed = true;
            m.redraw();
          }}
        />,
        -10
      );
    }
  });
});
