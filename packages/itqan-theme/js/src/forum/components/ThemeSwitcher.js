import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Dropdown from 'flarum/common/components/Dropdown';
import icon from 'flarum/common/helpers/icon';
import classList from 'flarum/common/utils/classList';
import extractText from 'flarum/common/utils/extractText';

import { MODES, currentMode, resolve, setMode } from '../utils/scheme';

export const ICONS = {
  light: 'fas fa-sun',
  dark: 'fas fa-moon',
  auto: 'fas fa-adjust',
};

/**
 * The header control.
 *
 * The menu is left unaligned on purpose: core's Dropdown measures the menu
 * against the viewport when it opens and flips it if it would overflow, which
 * is what keeps it on screen in a right-to-left layout. Pinning it with
 * `Dropdown-menu--right` would defeat that.
 *
 * Three choices rather than a two-state toggle: "follow my device" is a
 * distinct answer from either fixed scheme, and flipping between two states
 * cannot express it. The toggle icon shows the scheme currently on screen — so
 * under `auto` it is a sun by day and a moon by night, which is the honest
 * answer to "what am I looking at".
 */
export default class ThemeSwitcher extends Component {
  view() {
    const selected = currentMode();

    return (
      <Dropdown
        className="ThemeSwitcher"
        buttonClassName="Button Button--flat"
        menuClassName="ThemeSwitcher-menu"
        icon={ICONS[resolve(selected)]}
        caretIcon={false}
        label={extractText(app.translator.trans('itqan-theme.forum.switcher.title'))}
        accessibleToggleLabel={extractText(app.translator.trans('itqan-theme.forum.switcher.accessible_label'))}
      >
        {MODES.map((mode) => (
          <Button
            key={mode}
            className={classList('Button', { active: mode === selected })}
            icon={ICONS[mode]}
            aria-current={mode === selected ? 'true' : undefined}
            onclick={() => setMode(mode)}
          >
            {app.translator.trans(`itqan-theme.forum.switcher.${mode}`)}
            {icon('fas fa-check', {
              className: classList('ThemeSwitcher-check', { 'ThemeSwitcher-check--on': mode === selected }),
            })}
          </Button>
        ))}
      </Dropdown>
    );
  }
}
