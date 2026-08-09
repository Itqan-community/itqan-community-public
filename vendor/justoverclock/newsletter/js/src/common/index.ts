import app from 'flarum/common/app';

app.initializers.add('justoverclock/newsletter', () => {
  console.log('[justoverclock/newsletter] Hello, forum and admin!');
});
