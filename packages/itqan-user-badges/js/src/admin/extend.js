import Extend from 'flarum/common/extenders';
import commonExtend from '../common/extend';

export default [...commonExtend, new Extend.Admin()];
