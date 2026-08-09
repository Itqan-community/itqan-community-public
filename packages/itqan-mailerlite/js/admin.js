import app from 'flarum/admin/app';
import MailerLitePage from './src/admin/components/MailerLitePage';

app.initializers.add('itqan-mailerlite', () => {
  app.extensionData
    .for('itqan-mailerlite')
    .registerPage(MailerLitePage);
});
