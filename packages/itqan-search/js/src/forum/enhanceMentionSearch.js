import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';

const PAGE_LIMIT = 20;
const SCROLL_THRESHOLD_PX = 24;

export function normalizeMentionText(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/[\u064B-\u065F\u0670\u0640]/g, '')
    .replace(/[أإآٱ]/g, 'ا')
    .replace(/ة/g, 'ه')
    .replace(/ى/g, 'ي');
}

export function patchUserMentionable() {
  const userMentionable = app.mentionFormats?.mentionable?.('user');
  if (!userMentionable || userMentionable.__itqanMentionSearchPatched) return userMentionable;

  userMentionable.__itqanMentionSearchPatched = true;
  userMentionable.__itqanSearchState = {
    token: 0,
    refreshedToken: 0,
    query: '',
    offset: 0,
    hasMore: false,
    loading: false,
    seenIds: new Set(),
    onSearchStart: null,
    onSearchEnd: null,
    onSearchComplete: null,
  };

  // Match the backend SmartUserFullTextGambit: substring, spaceless
  // (@محمداحمد -> محمد احمد), multi-word (@"حسن محمد"), and Arabic
  // normalization (أ/إ/آ -> ا, ة -> ه, ى -> ي, tashkeel stripped).
  userMentionable.matches = function (model, typed) {
    if (!typed) return false;

    const query = normalizeMentionText(typed).trim();
    if (!query) return false;

    const spacelessQuery = query.replace(/\s+/g, '');
    const words = query.split(/\s+/).filter(Boolean);
    const names = [model.username?.(), model.displayName?.()].filter(Boolean).map(normalizeMentionText);

    if (names.some((name) => name.includes(query) || (spacelessQuery && name.replace(/\s+/g, '').includes(spacelessQuery)))) {
      return true;
    }

    if (words.length > 1) {
      const combined = names.join(' ');
      return words.every((w) => combined.includes(w));
    }

    return false;
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
    const state = userMentionable.__itqanSearchState;
    const token = ++state.token;
    const query = String(typed || '').toLowerCase();

    if (typeof state.onSearchStart === 'function') {
      state.onSearchStart(token, query);
    }

    try {
      const { users, hasMore } = await userMentionable.searchPage(typed, 0, PAGE_LIMIT);

      if (token === state.token) {
        state.query = query;
        state.offset = users.length;
        state.hasMore = users.length > 0 && hasMore;
        state.loading = false;
        state.seenIds = new Set(users.map((u) => String(u.id())));

        // flarum/mentions throttles MentionableModels.search() with 250ms; if a
        // 2nd character is typed during cooldown, throttle() returns undefined and
        // `.then(buildSuggestions)` is never attached. Trigger a single input pass
        // on the next tick (after MentionableModels records `this.searched`) so
        // the dropdown always renders the newly fetched results.
        if (typeof state.onSearchComplete === 'function') {
          const cb = state.onSearchComplete;
          setTimeout(() => cb(token, query), 0);
        }
      }

      return users;
    } finally {
      if (token === state.token && typeof state.onSearchEnd === 'function') {
        state.onSearchEnd(token);
      }
    }
  };

  return userMentionable;
}

function getActiveAtMention(editor) {
  if (!editor?.getSelectionRange || !editor?.getLastNChars) return null;

  const selection = editor.getSelectionRange();
  const cursor = selection[0];
  if (selection[1] - cursor > 0) return null;

  const lastChunk = editor.getLastNChars(30);
  for (let i = lastChunk.length - 1; i >= 0; i--) {
    const character = lastChunk.substr(i, 1);
    const activeFormat = app.mentionFormats?.get?.(character);

    if (activeFormat && (i === 0 || /\s/.test(lastChunk.substr(i - 1, 1)))) {
      if (character !== '@') return null;

      const typed = lastChunk.substring(i + 1).toLowerCase();
      const query = activeFormat.queryFromTyped(typed);
      if (!query) return null;

      return {
        absMentionStart: cursor - lastChunk.length + i + 1,
        query,
      };
    }
  }

  return null;
}

export default function enhanceMentionSearch(TextEditorTarget) {
  patchUserMentionable();

  // Remove any DOM-appended pagination items before flarum/mentions runs
  // Mithril's m.render() on the next keystroke.
  extend(TextEditorTarget, 'buildEditorParams', function (params) {
    params.inputListeners?.unshift(() => {
      this.$('.ComposerBody-mentionsDropdownContainer li[data-itqan-extra="1"]').remove();
      this.$('.ComposerBody-mentionsDropdownContainer .itqan-mention-overlay').remove();
    });
  });

  extend(TextEditorTarget, 'oncreate', function () {
    const userMentionable = patchUserMentionable();

    const $container = this.$('.ComposerBody-mentionsDropdownContainer');
    const $editor = this.$('.TextEditor-editor');
    if (!$container.length || !$editor.length) return;

    const getEditor = () => this.attrs?.composer?.editor;

    const hideLoading = () => {
      $container.find('li.itqan-mention-loading').remove();
    };

    const hideOverlay = () => {
      $container.find('.itqan-mention-overlay').remove();
    };

    const showOverlay = () => {
      const $dropdown = $container.find('.MentionsDropdown');
      if (!$dropdown.length || !$dropdown.is(':visible')) return;
      if ($container.find('.itqan-mention-overlay').length) return;

      const el = $dropdown[0];
      const overlay = document.createElement('div');
      overlay.className = 'itqan-mention-overlay';
      overlay.style.cssText = [
        'position:absolute',
        `left:${el.offsetLeft}px`,
        `top:${el.offsetTop}px`,
        `width:${$dropdown.outerWidth()}px`,
        `height:${$dropdown.outerHeight()}px`,
        'display:flex',
        'align-items:center',
        'justify-content:center',
        'background:rgba(0,0,0,0.28)',
        'backdrop-filter:blur(1px)',
        'border-radius:6px',
        'z-index:1050',
        'pointer-events:none',
      ].join(';');
      overlay.innerHTML =
        '<span style="display:inline-flex;align-items:center;justify-content:center;padding:8px 12px;border-radius:999px;background:var(--body-bg,#fff);color:var(--text-color,#111);box-shadow:0 2px 8px rgba(0,0,0,0.2);"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i></span>';
      $container[0].appendChild(overlay);
    };

    const showLoading = ($dropdown) => {
      const $target = $dropdown && $dropdown.length ? $dropdown : $container.find('.MentionsDropdown');
      if (!$target.length || !$target.is(':visible')) return;
      if ($target.find('li.itqan-mention-loading').length) return;

      const li = document.createElement('li');
      li.className = 'itqan-mention-loading';
      li.setAttribute('data-itqan-extra', '1');
      li.style.cssText = 'text-align:center;padding:6px 0;opacity:0.7;';
      li.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>';
      $target[0].appendChild(li);
    };

    if (userMentionable?.__itqanSearchState) {
      userMentionable.__itqanSearchState.onSearchStart = () => showOverlay();
      userMentionable.__itqanSearchState.onSearchEnd = () => hideOverlay();
      userMentionable.__itqanSearchState.onSearchComplete = (token, query) => {
        const state = userMentionable.__itqanSearchState;
        if (state.refreshedToken === token) return;
        const active = getActiveAtMention(getEditor());
        if (active && active.query === query) {
          state.refreshedToken = token;
          $editor.trigger('input');
        }
      };
    }

    const appendExtraUsers = (users, query, $dropdown, state) => {
      const um = patchUserMentionable();
      if (!um || !$dropdown.length) return;

      for (const user of users) {
        const id = String(user.id());
        if (state.seenIds.has(id)) continue;
        state.seenIds.add(id);

        const li = document.createElement('li');
        li.setAttribute('data-itqan-extra', '1');

        const content = um.suggestion(user, query);
        const replacement = um.replacement(user);

        m.render(
          li,
          <button
            type="button"
            className="MentionsDropdownItem PostPreview MentionsDropdown-user"
            onmouseenter={() => {
              $dropdown.find('li').removeClass('active');
              $(li).addClass('active');
            }}
            onclick={() => {
              const editor = getEditor();
              const active = getActiveAtMention(editor);
              if (editor && active) {
                editor.replaceBeforeCursor(active.absMentionStart - 1, replacement + ' ');
              }
              $editor.trigger($.Event('keydown', { which: 27, keyCode: 27, key: 'Escape' }));
            }}
          >
            <span className="PostPreview-content">{content}</span>
          </button>
        );

        $dropdown[0].appendChild(li);
      }
    };

    const loadNextPage = async ($dropdown) => {
      const um = patchUserMentionable();
      const state = um?.__itqanSearchState;
      const active = getActiveAtMention(getEditor());

      if (!um || !state || !active) return;
      if (state.query !== active.query || !state.hasMore || state.loading) return;

      const activeToken = state.token;
      const activeQuery = state.query;
      state.loading = true;
      showLoading($dropdown);

      try {
        const { users, hasMore } = await um.searchPage(activeQuery, state.offset, PAGE_LIMIT);
        const current = getActiveAtMention(getEditor());

        if (activeToken !== state.token || !current || current.query !== activeQuery || !$dropdown.is(':visible')) {
          return;
        }

        hideLoading();
        state.offset += users.length;
        state.hasMore = users.length > 0 && hasMore;

        appendExtraUsers(users, activeQuery, $dropdown, state);
      } catch (e) {
        // Keep currently loaded results if a subsequent page fails.
      } finally {
        hideLoading();
        if (activeToken === state.token) {
          state.loading = false;
        }
      }
    };

    const bindDropdownScroll = () => {
      const $dropdown = $container.find('.MentionsDropdown');
      if (!$dropdown.length || $dropdown.data('itqanMentionScrollBound')) return;

      $dropdown.data('itqanMentionScrollBound', true);
      $dropdown.on('scroll.itqanMention', function () {
        if (this.scrollTop + this.clientHeight >= this.scrollHeight - SCROLL_THRESHOLD_PX) {
          loadNextPage($dropdown);
        }
      });
    };

    bindDropdownScroll();

    if (typeof MutationObserver !== 'undefined') {
      this.__itqanMentionObserver = new MutationObserver(bindDropdownScroll);
      this.__itqanMentionObserver.observe($container[0], { childList: true });
    }
  });

  extend(TextEditorTarget, 'onremove', function () {
    if (this.__itqanMentionObserver) {
      this.__itqanMentionObserver.disconnect();
      this.__itqanMentionObserver = null;
    }
  });
}
