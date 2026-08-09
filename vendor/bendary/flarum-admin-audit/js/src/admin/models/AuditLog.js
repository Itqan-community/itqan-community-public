import Model from 'flarum/common/Model';

export default class AuditLog extends Model {
  category = Model.attribute('category');
  action = Model.attribute('action');
  target = Model.attribute('target');
  oldValue = Model.attribute('oldValue');
  newValue = Model.attribute('newValue');
  meta = Model.attribute('meta');
  ip = Model.attribute('ip');
  createdAt = Model.attribute('createdAt', Model.transformDate);
  
  user = Model.hasOne('user');
}
