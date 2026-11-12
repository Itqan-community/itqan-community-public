import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';

const PAGE_LIMIT = 20;
const SCROLL_THRESHOLD_PX = 24;
const SEARCH_DEBOUNCE_MS = 180;

export function patchUserMentionable() {
  const userMentionable = app.mentionFormats?.mentionable?.('user');
  if (!userMentionable || userMentionable.__itqanMentionSearchPatched) return userMentionable;

  userMentionable.__itqanMentionSearchPatched = true;

  // Backend FulltextFilter / NicknameFullTextFilter already match anywhere in
  // username/nickname and sort prefix matches first. Match the same substring
  // semantics on the client so middle-of-name results are not filtered out.
  userMentionable.matches = function (model, typed) {
    if (!typed) return false;

    const query = String(typed).toLowerCase();
    const names = [model.username?.(), model.displayName?.()].filter(Boolean);

    return names.some((name) => String(name).toLowerCase().includes(query));
  };

  userMentionable.searchPage = async function (typed, offset = 0, limit = PAGE_LIMIT) {
    const results = await app.store.find('users', {
      filter: { q: typed },
      page: { limit, offset },
    });

    const users = Array.isArray(results) ? results : Array.from(results || []);
    const hasMore = Boolean(results?.payload?.links?.next) || users.length >= limit;

    return { users, hasMore };
  };

  userMentionable.search = async function (typed) {
    const { users } = await userMentionable.searchPage(typed, 0, PAGE_LIMIT);
    return users;
  };

  return userMentionable;
}

export default function enhanceMentionSearch(TextEditorTarget) {
  patchUserMentionable();

  extend(TextEditorTarget, 'onbuild', function () {
    patchUserMentionable();

    const editor = this;
    const state = {
      token: 0,
      timer: null,
      query: '',
      offset: 0,
      hasMore: false,
      loading: false,
      mentionables: null,
    };

    const loadNextPage = async () => {
      const userMentionable = patchUserMentionable();
      if (!userMentionable || !state.hasMore || state.loading || !state.query || !state.mentionables) {
        return;
      }

      const activeToken = state.token;
      const activeQuery = state.query;
      state.loading = true;

      try {
        const { users, hasMore } = await userMentionable.searchPage(activeQuery, state.offset, PAGE_LIMIT);
        if (activeToken !== state.token) return;

        const userResults = state.mentionables.results?.user;
        if (userResults instanceof Map) {
          for (const user of users) {
            const id = String(user.id());
            if (!userResults.has(id)) {
              userResults.set(id, user);
            }
          }
        }

        state.offset += users.length;
        state.hasMore = users.length > 0 && hasMore;

        const container = editor.$('.ComposerBody-mentionsDropdownContainer')[0];
        const $dropdown = editor.mentionsDropdown?.$?.();
        if (container && $dropdown?.length && editor.mentionsDropdown.active) {
          const prevScrollTop = $dropdown[0].scrollTop;
          editor.mentionsDropdown.items = state.mentionables.buildSuggestions();
          m.render(container, editor.mentionsDropdown.render());
          $dropdown[0].scrollTop = prevScrollTop;
        }
      } catch (e) {
        // Keep currently loaded results if a subsequent page fails.
      } finally {
        if (activeToken === state.token) {
          state.loading = false;
        }
      }
    };

    const bindDropdownScroll = () => {
      const $dropdown = editor.mentionsDropdown?.$?.();
      if (!$dropdown?.length || $dropdown.data('itqanMentionScrollBound')) return;

      $dropdown.data('itqanMentionScrollBound', true);
      $dropdown.on('scroll.itqanMention', function () {
        if (this.scrollTop + this.clientHeight >= this.scrollHeight - SCROLL_THRESHOLD_PX) {
          loadNextPage();
        }
      });
    };

    this.searchMentions = (mentionables, buildSuggestions) => {
      const userMentionable = patchUserMentionable();
      const hasUserMentionable = mentionables?.mentionables?.some((m) => m.type() === 'user' && m.enabled());

      if (!hasUserMentionable || !userMentionable) {
        return mentionables.search().then(buildSuggestions);
      }

      const query = String(mentionables.typed || '').trim();
      if (!query) return;

      const token = ++state.token;
      clearTimeout(state.timer);

      state.timer = setTimeout(async () => {
        if (token !== state.token) return;

        state.query = query;
        state.offset = 0;
        state.hasMore = false;
        state.loading = true;
        state.mentionables = mentionables;

        try {
          const { users, hasMore } = await userMentionable.searchPage(query, 0, PAGE_LIMIT);
          if (token !== state.token) return;

          if (mentionables.results) {
            mentionables.results.user = new Map(users.map((u) => [String(u.id()), u]));
          }

          state.offset = users.length;
          state.hasMore = users.length > 0 && hasMore;
          state.loading = false;

          buildSuggestions();
          bindDropdownScroll();
        } catch (e) {
          if (token === state.token) {
            state.loading = false;
          }
        }
      }, SEARCH_DEBOUNCE_MS);
    };
  });
}
