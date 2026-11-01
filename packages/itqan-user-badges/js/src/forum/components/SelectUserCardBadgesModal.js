import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import ItemList from 'flarum/common/utils/ItemList';
import Switch from 'flarum/common/components/Switch';
import UserBadge from '../../common/components/UserBadge';
import categorizeUserBadges from '../utils/categorizeUserBadges';
import Tooltip from 'flarum/common/components/Tooltip';
import app from 'flarum/forum/app';

export default class SelectUserCardBadgesModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.loading = false;

    this.limit = app.forum.attribute('numberOfBadgesOnUserCard');
    this.selectedBadges = (this.attrs.user.userBadges() || [])
      .filter((userBadge) => userBadge && userBadge.inUserCard())
      .map((userBadge) => userBadge.id());

    // Flarum 2 does not ship a member's badges with every user payload, so load
    // them here when the card was opened from a post or the discussion list.
    if (!this.attrs.user.userBadges()) {
      this.loading = true;

      app.store
        .find('users', this.attrs.user.id(), { include: 'userBadges,userBadges.badge,userBadges.badge.category' })
        .then(() => {
          this.selectedBadges = (this.attrs.user.userBadges() || [])
            .filter((userBadge) => userBadge && userBadge.inUserCard())
            .map((userBadge) => userBadge.id());
        })
        .finally(() => {
          this.loading = false;
          m.redraw();
        });
    }
  }

  className() {
    return 'Modal--big UserCardBadgesModal';
  }

  title() {
    return app.translator.trans('v17development-flarum-badges.forum.badges_in_card.title');
  }

  content() {
    return (
      <div>
        <div className="Modal-body">{this.data().toArray()}</div>
        <div className="Modal-footer">
          <Button className={'Button Button--primary'} onclick={() => this.save()} loading={this.loading}>
            {app.translator.trans('v17development-flarum-badges.forum.save_changes')}
          </Button>
        </div>
      </div>
    );
  }

  data() {
    const items = new ItemList();

    const categories = categorizeUserBadges(this.attrs.user);

    // Badge name
    items.add(
      'info',
      <div className={'BadgeModalListItem'}>
        <p>{app.translator.trans('v17development-flarum-badges.forum.badges_in_card.description')}</p>
        <p>
          {app.translator.trans('v17development-flarum-badges.forum.badges_in_card.select_limit', {
            limit: app.forum.attribute('numberOfBadgesOnUserCard'),
          })}
        </p>
      </div>
    );

    // Loop through all badge categories
    categories.map(({ name, category, badges }) => {
      items.add(
        `badge_category_${category ? category.id() : 'uncategorized'}`,
        <div className={'UserCardBadgesModalCategory'}>
          <div className={'UserCardBadgesModalCategory-name'}>{name}</div>

          {/* Loop through the badges */}
          {badges
            .sort((a, b) => a.badge().order() - b.badge().order())
            .map((userBadge) => {
              const disabled = this.selectedBadges.length >= this.limit && this.selectedBadges.indexOf(userBadge.id()) === -1;

              return (
                <Tooltip
                  text={
                    disabled
                      ? app.translator.trans('v17development-flarum-badges.forum.badges_in_card.hit_limit', {
                          limit: app.forum.attribute('numberOfBadgesOnUserCard'),
                        })
                      : ''
                  }
                  position="bottom"
                >
                  <div
                    className={'UserCardBadgesModalCategory-badge'}
                    onclick={() => {
                      if (disabled) {
                        return;
                      }

                      const currentIndex = this.selectedBadges.indexOf(userBadge.id());

                      // Remove from selected list
                      if (currentIndex >= 0) {
                        this.selectedBadges.splice(currentIndex, 1);
                      } else {
                        // Add to list
                        this.selectedBadges.push(userBadge.id());
                      }
                    }}
                  >
                    <div className={'UserCardBadgesModalCategory-badge-preview'}>
                      <UserBadge badge={userBadge.badge()} onclick={() => {}} />
                    </div>
                    {/* <div className={"UserCardBadgesModalCategory-badge-points"}></div> */}
                    <div className={'UserCardBadgesModalCategory-badge-switch'}>
                      {Switch.component({
                        state: this.selectedBadges.indexOf(userBadge.id()) >= 0,
                        disabled,
                      })}
                    </div>
                  </div>
                </Tooltip>
              );
            })}
        </div>
      );
    });

    return items;
  }

  save() {
    this.loading = true;

    this.attrs.user
      .save({ userCardBadges: this.selectedBadges })
      .then(() => {
        // Update current user badges store
        (this.attrs.user.userBadges() || []).forEach((userBadge) => {
          userBadge.pushAttributes({
            inUserCard: this.selectedBadges.indexOf(userBadge.id()) >= 0,
          });
        });

        this.hide();
      })
      .catch(() => {})
      .then(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
