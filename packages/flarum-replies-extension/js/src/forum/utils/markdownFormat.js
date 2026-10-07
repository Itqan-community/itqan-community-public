// Pure helpers for the quick reply editor: wrap or prefix the current textarea
// selection with Markdown markers and report the selection to restore.

export const MARKDOWN_FORMATS = {
  bold: { before: '**', after: '**' },
  italic: { before: '*', after: '*' },
  link: { before: '[', after: '](url)' },
};

export function applyMarkdown(value, start, end, key) {
  const text = String(value == null ? '' : value);
  const from = Math.max(0, Math.min(Number(start) || 0, text.length));
  const to = Math.max(from, Math.min(Number(end) || 0, text.length));

  if (key === 'quote') {
    return quoteSelection(text, from, to);
  }

  const format = MARKDOWN_FORMATS[key];
  if (!format) {
    return { value: text, selectionStart: from, selectionEnd: to };
  }

  const selected = text.slice(from, to);
  const next = text.slice(0, from) + format.before + selected + format.after + text.slice(to);

  if (key === 'link') {
    // Put the caret on the placeholder URL so the author can paste it.
    const urlStart = from + format.before.length + selected.length + 2;
    return { value: next, selectionStart: urlStart, selectionEnd: urlStart + 3 };
  }

  return {
    value: next,
    selectionStart: from + format.before.length,
    selectionEnd: from + format.before.length + selected.length,
  };
}

function quoteSelection(text, from, to) {
  const lineStart = text.lastIndexOf('\n', Math.max(0, from - 1)) + 1;
  let lineEnd = text.indexOf('\n', to);
  if (lineEnd === -1) lineEnd = text.length;

  const block = text.slice(lineStart, lineEnd);
  const quoted = block
    .split('\n')
    .map((line) => `> ${line}`)
    .join('\n');

  return {
    value: text.slice(0, lineStart) + quoted + text.slice(lineEnd),
    selectionStart: lineStart,
    selectionEnd: lineStart + quoted.length,
  };
}
