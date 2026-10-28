import Avatar from 'flarum/common/components/Avatar';

// Flarum 2.0 replaced the avatar() helper with the Avatar component.
export default function avatar(user, attrs = {}) {
  return m(Avatar, { user, ...attrs });
}
