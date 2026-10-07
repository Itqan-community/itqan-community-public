// Full six-form Arabic pluralization (تفقيط) for core/likes/mentions count
// strings. Frontend overrides so the UI always agrees with the number, even if
// the compiled language-pack catalogue lags behind the source.

export const ARABIC_COUNT_TRANSLATIONS = {
  'core.forum.post_scrubber.unread_text':
    '{count, plural, zero {لا تعليقات غير مقروءة} one {تعليق واحد غير مقروء} two {تعليقان غير مقروءان} few {# تعليقات غير مقروءة} many {# تعليقاً غير مقروء} other {# تعليق غير مقروء}}',
  'core.forum.post_scrubber.viewing_text':
    '{count, plural, zero {{index} من {formattedCount} تعليق} one {{index} من {formattedCount} تعليق واحد} two {{index} من {formattedCount} تعليقين} few {{index} من {formattedCount} تعليقات} many {{index} من {formattedCount} تعليقاً} other {{index} من {formattedCount} تعليق}}',
  'core.forum.discussion_list.total_replies_a11y_label':
    '{count, plural, zero {لا ردود} one {رد واحد} two {ردان} few {# ردود} many {# رداً} other {# رد}}',
  'core.forum.discussion_list.unread_replies_a11y_label':
    '{count, plural, zero {لا ردود غير مقروءة} one {رد واحد غير مقروء} two {ردان غير مقروءان} few {# ردود غير مقروءة} many {# رداً غير مقروء} other {# رد غير مقروء}}',
  'flarum-likes.forum.post.liked_by_text':
    '{count, plural, zero {{users} معجبون} one {{users} معجب} two {{users} معجبان} few {{users} معجبون} many {{users} معجبون} other {{users} معجبون}}.',
  'flarum-mentions.forum.post.mentioned_by_more_text':
    '{count, plural, zero {لا ردود إضافية} one {رد إضافي واحد} two {ردان إضافيان} few {# ردود إضافية} many {# رداً إضافياً} other {# رد إضافي}}',
};

/**
 * Register the Arabic count overrides with the front-end translator.
 * No-op for non-Arabic locales.
 */
export function applyArabicCountOverrides(app) {
  if (!app || !app.translator || typeof app.translator.addTranslations !== 'function') return;
  if (!String(app.translator.locale || '').startsWith('ar')) return;

  app.translator.addTranslations(ARABIC_COUNT_TRANSLATIONS);
}

export default applyArabicCountOverrides;
