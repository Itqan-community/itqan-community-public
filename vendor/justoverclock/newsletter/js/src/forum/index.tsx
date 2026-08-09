// @ts-nocheck
import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexPage from 'flarum/forum/components/IndexPage'
import NewsLetterBox from "./components/NewsLetterBox";
import NewsLetterOptOutPage from "./components/pages/NewsLetterOptOutPage";

app.initializers.add('justoverclock/newsletter', () => {
  app.routes.newsLetterOptOut = {
    path: '/newsletter/opt-out',
    component: NewsLetterOptOutPage
  }
  extend(IndexPage.prototype, 'sidebarItems', function (items) {
    items.add(
      'newsLetterBox',
      <NewsLetterBox />
    )
  })
});
