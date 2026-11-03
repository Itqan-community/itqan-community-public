import Model from 'flarum/common/Model';

export default class BadgeCategory extends Model {
  name = Model.attribute('name');
  order = Model.attribute('order');
  description = Model.attribute('description');
  isEnabled = Model.attribute('isEnabled');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  isTable = Model.attribute('isTable');

  badges = Model.hasMany('badges');
}
