import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import SessionDropdown from 'flarum/forum/components/SessionDropdown';
import SettingsPage from 'flarum/forum/components/SettingsPage';
import FieldSet from 'flarum/common/components/FieldSet';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';

import ThemeSwitcher, { ICONS } from './components/ThemeSwitcher';
import PwaInstallPrompt from './components/PwaInstallPrompt';
import addImageLightbox from './addImageLightbox';
import addReplyAffordances from './addReplyAffordances';
import { MODES, boot, currentMode, setMode } from './utils/scheme';
import { applyArabicCountOverrides } from './utils/arabicCounts';

export { default as ThemeSwitcher } from './components/ThemeSwitcher';
export { default as PwaInstallPrompt } from './components/PwaInstallPrompt';
export { default as ImageLightbox } from './components/ImageLightbox';
export { default as addImageLightbox } from './addImageLightbox';
export * from './utils/scheme';

app.initializers.add('itqan-theme', () => {
  boot();
  applyArabicCountOverrides(app);
  addImageLightbox();
  addReplyAffordances();

  // Next to search and notifications: the same place the language selector
  // lives, which is the closest existing analogue to this control.
  extend(HeaderSecondary.prototype, 'items', function (items) {
    items.add('itqanTheme', <ThemeSwitcher />, 25);
  });

  extend(SessionDropdown.prototype, 'items', function (items) {
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

  // The header control is the fast path; this is where a reader looks when
  // they go hunting for a setting they half-remember.
  extend(SettingsPage.prototype, 'settingsItems', function (items) {
    items.add(
      'itqanTheme',
      <FieldSet className="Settings-theme" label={app.translator.trans('itqan-theme.forum.settings.heading')}>
        <div className="Settings-theme-options">
          {MODES.map((mode) => (
            <Button
              key={mode}
              className={classList('Button', { active: mode === currentMode() })}
              icon={ICONS[mode]}
              aria-current={mode === currentMode() ? 'true' : undefined}
              onclick={() => setMode(mode)}
            >
              {app.translator.trans(`itqan-theme.forum.switcher.${mode}`)}
            </Button>
          ))}
        </div>
      </FieldSet>,
      5
    );

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
});
