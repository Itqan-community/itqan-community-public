/**
 * Detect an in-progress `@` mention immediately before the cursor.
 *
 * Mirrors Flarum's composer trigger rules: the `@` must start the text or be
 * preceded by whitespace, and the typed query must not contain whitespace.
 *
 * @param {string} value  Full textarea value.
 * @param {number} cursor Cursor position (selectionStart).
 * @returns {{start: number, query: string} | null} `start` is the index of `@`.
 */
export function detectMention(value, cursor) {
  if (typeof value !== 'string') return null;

  const pos = typeof cursor === 'number' ? cursor : value.length;
  const before = value.slice(0, pos);
  const at = before.lastIndexOf('@');

  if (at === -1) return null;

  // Must be at the start of the text or preceded by whitespace (so an email
  // like "a@b" never triggers).
  if (at > 0 && !/\s/.test(before[at - 1])) return null;

  const query = before.slice(at + 1);

  // Whitespace ends the mention.
  if (/\s/.test(query)) return null;

  return { start: at, query };
}

export default detectMention;
