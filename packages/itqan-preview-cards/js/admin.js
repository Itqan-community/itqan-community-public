import app from 'flarum/admin/app';
import PreviewCardsPage from './src/admin/components/PreviewCardsPage';

app.initializers.add('itqan-preview-cards', () => {
  app.registry.for('itqan-preview-cards').registerPage(PreviewCardsPage);
});
