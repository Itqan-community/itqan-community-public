import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import HeaderSecondary from 'flarum/forum/components/HeaderSecondary';
import SettingsPage from 'flarum/forum/components/SettingsPage';
import FieldSet from 'flarum/common/components/FieldSet';
import Button from 'flarum/common/components/Button';
import classList from 'flarum/common/utils/classList';

import ThemeSwitcher, { ICONS } from './components/ThemeSwitcher';
import addImageLightbox from './addImageLightbox';
import { MODES, boot, currentMode, setMode } from './utils/scheme';

export { default as ThemeSwitcher } from './components/ThemeSwitcher';
export { default as ImageLightbox } from './components/ImageLightbox';
export { default as addImageLightbox } from './addImageLightbox';
export * from './utils/scheme';

app.initializers.add('itqan-theme', () => {
  boot();
  addImageLightbox();

  // Next to search and notifications: the same place the language selector
  // lives, which is the closest existing analogue to this control.
  extend(HeaderSecondary.prototype, 'items', function (items) {
    items.add('itqanTheme', <ThemeSwitcher />, 25);
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
  });
});
