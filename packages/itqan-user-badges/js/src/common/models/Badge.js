import Model from 'flarum/common/Model';

export default class Badge extends Model {
  name = Model.attribute('name');
  icon = Model.attribute('icon');
  order = Model.attribute('order');
  image = Model.attribute('image');
  description = Model.attribute('description');
  isVisible = Model.attribute('isVisible');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  earnedAmount = Model.attribute('earnedAmount');
  backgroundColor = Model.attribute('backgroundColor');
  iconColor = Model.attribute('iconColor');
  labelColor = Model.attribute('labelColor');

  category = Model.hasOne('category');
  users = Model.hasMany('users');
}
