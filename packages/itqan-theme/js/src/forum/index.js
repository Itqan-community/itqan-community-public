import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import FieldSet from 'flarum/common/components/FieldSet';
import Button from 'flarum/common/components/Button';

import PwaInstallPrompt from './components/PwaInstallPrompt';
import AbsoluteDateBadge from './components/AbsoluteDateBadge';
import addRichToolbarButtons from './components/RichToolbarButtons';
import addImageLightbox from './addImageLightbox';
import addReplyAffordances from './addReplyAffordances';
import addMessagePolish from './addMessagePolish';
import addModalLabels from './addModalLabels';
import { applyArabicCountOverrides } from './utils/arabicCounts';

export { default as PwaInstallPrompt } from './components/PwaInstallPrompt';
export { default as ImageLightbox } from './components/ImageLightbox';
export { default as addImageLightbox } from './addImageLightbox';
export { default as AbsoluteDateBadge } from './components/AbsoluteDateBadge';
export { default as addRichToolbarButtons } from './components/RichToolbarButtons';

app.initializers.add('itqan-theme', () => {
  applyArabicCountOverrides(app);
  addImageLightbox();
  addReplyAffordances();
  addMessagePolish();
  addModalLabels();

  // Colour scheme switching is Flarum 2.0's own (header + settings), driven by
  // `data-theme`. The theme styles that attribute; it does not manage it.

  extend('flarum/forum/components/SessionDropdown', 'items', function (items) {
    items.add(
      'pwaInstall',
      <Button
        icon="fas fa-mobile-alt"
        onclick={() => PwaInstallPrompt.openModal()}
      >
        {app.translator.trans('itqan-theme.forum.pwa.menu')}
      </Button>,
      -10
    );
  });

  extend('flarum/forum/components/SettingsPage', 'settingsItems', function (items) {
    items.add(
      'itqanPwa',
      <FieldSet className="Settings-pwa" label={app.translator.trans('itqan-theme.forum.pwa.settings_heading')}>
        <Button
          className="Button Button--primary"
          icon="fas fa-download"
          onclick={() => PwaInstallPrompt.openModal()}
        >
          {app.translator.trans('itqan-theme.forum.pwa.settings_description')}
        </Button>
      </FieldSet>,
      4
    );
  });

  // ==========================================
  // Archival post absolute timestamps (moved from itqan-discussions, PR #20)
  // ==========================================
  extend('flarum/forum/components/PostMeta', 'viewItems', function (items) {
    const post = this.attrs.post;
    if (!post || !post.createdAt()) return;
    const time = post.createdAt();

    // Flarum 2.0 removed ItemList::replace(); remove + add is the equivalent.
    items.remove('time');
    items.add(
      'time',
      // A button, not an anchor: this toggle has no href, and an anchor
      // without one is not crawlable (Lighthouse: crawlable-anchors). Bootstrap
      // dropdowns work the same with either element.
      <button type="button" className="Dropdown-toggle" onclick={(e) => this.selectPermalink(e)} data-toggle="dropdown">
        <AbsoluteDateBadge time={time} />
      </button>,
      100
    );
  });

  // ==========================================
  // Rich composer toolbar: table / quote / code buttons (moved from itqan-composer-tools, PR #12)
  // ==========================================
  addRichToolbarButtons();
});
