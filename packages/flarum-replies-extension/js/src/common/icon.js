import Icon from 'flarum/common/components/Icon';

// Flarum 2.0 replaced the icon() helper with the Icon component.
export default function icon(name, attrs = {}) {
  return m(Icon, { name, ...attrs });
}
