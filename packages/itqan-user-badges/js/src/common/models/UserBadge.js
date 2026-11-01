import Model from 'flarum/common/Model';

export default class UserBadge extends Model {
  user = Model.hasOne('user');
  badge = Model.hasOne('badge');
  description = Model.attribute('description');
  isPrimary = Model.attribute('isPrimary');
  assignedAt = Model.attribute('assignedAt', Model.transformDate);
  inUserCard = Model.attribute('inUserCard');
}
