import app from 'flarum/admin/app';
import PreviewCardsPage from './src/admin/components/PreviewCardsPage';

app.initializers.add('itqan-preview-cards', () => {
  app.extensionData.for('itqan-preview-cards').registerPage(PreviewCardsPage);
});
