import app from 'flarum/admin/app';
import SendEmailPage from "./components/SendEmailPage";

app.initializers.add('justoverclock/newsletter', () => {
  app
    .extensionData
    .for('justoverclock-newsletter')
    .registerPage(SendEmailPage)
});
