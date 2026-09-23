export const SORT_PREFERENCE_KEY = 'mtareq-nested-replies.sortMode';
export const DEFAULT_SORT = 'oldest';
export const VALID_SORTS = ['oldest', 'newest', 'top', 'replies'];

const resolveStorage = (storage) => {
  if (storage) return storage;
  try {
    if (typeof localStorage !== 'undefined') return localStorage;
  } catch (e) {
    /* storage access can throw in some privacy modes */
  }
  return null;
};

export function readSortPreference(storage) {
  const s = resolveStorage(storage);
  if (!s) return DEFAULT_SORT;
  try {
    const value = s.getItem(SORT_PREFERENCE_KEY);
    return VALID_SORTS.includes(value) ? value : DEFAULT_SORT;
  } catch (e) {
    return DEFAULT_SORT;
  }
}

export function writeSortPreference(mode, storage) {
  if (!VALID_SORTS.includes(mode)) return;
  const s = resolveStorage(storage);
  if (!s) return;
  try {
    s.setItem(SORT_PREFERENCE_KEY, mode);
  } catch (e) {
    /* ignore quota / private-mode errors */
  }
}
