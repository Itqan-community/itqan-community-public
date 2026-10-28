import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import FieldSet from 'flarum/common/components/FieldSet';
import Button from 'flarum/common/components/Button';

import PwaInstallPrompt from './components/PwaInstallPrompt';
import AbsoluteDateBadge from './components/AbsoluteDateBadge';
import addRichToolbarButtons from './components/RichToolbarButtons';
import addImageLightbox from './addImageLightbox';
import addReplyAffordances from './addReplyAffordances';
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

  // Colour scheme switching is Flarum 2.0's own (header + settings), driven by
  // `data-theme`. The theme styles that attribute; it does not manage it.

  extend('flarum/forum/components/SessionDropdown', 'items', function (items) {
    items.add(
      'pwaInstall',
      <Button
        icon="fas fa-mobile-alt"
        onclick={() => PwaInstallPrompt.openModal()}
      >
        تثبيت التطبيق
      </Button>,
      -10
    );
  });

  extend('flarum/forum/components/SettingsPage', 'settingsItems', function (items) {
    items.add(
      'itqanPwa',
      <FieldSet className="Settings-pwa" label="تطبيق مجتمع إتقان">
        <Button
          className="Button Button--primary"
          icon="fas fa-download"
          onclick={() => PwaInstallPrompt.openModal()}
        >
          تثبيت التطبيق على جهازك
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
      <a className="Dropdown-toggle" onclick={(e) => this.selectPermalink(e)} data-toggle="dropdown">
        <AbsoluteDateBadge time={time} />
      </a>,
      100
    );
  });

  // ==========================================
  // Rich composer toolbar: table / quote / code buttons (moved from itqan-composer-tools, PR #12)
  // ==========================================
  addRichToolbarButtons();
});
