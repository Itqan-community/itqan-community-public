import app from 'flarum/admin/app';
import PrettyMailPage from './src/admin/components/PrettyMailPage';

app.initializers.add('itqan-pretty-mail', () => {
  app.registry.for('itqan-pretty-mail').registerPage(PrettyMailPage);
});
