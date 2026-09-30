import { describe, it, expect } from 'vitest';
import { applyMarkdown } from './markdownFormat';

describe('applyMarkdown', () => {
  it('wraps the selection in bold markers and keeps it selected', () => {
    expect(applyMarkdown('hello world', 0, 5, 'bold')).toEqual({
      value: '**hello** world',
      selectionStart: 2,
      selectionEnd: 7,
    });
  });

  it('inserts empty bold markers with the caret in the middle', () => {
    expect(applyMarkdown('ab', 1, 1, 'bold')).toEqual({
      value: 'a****b',
      selectionStart: 3,
      selectionEnd: 3,
    });
  });

  it('wraps the selection in italic markers', () => {
    expect(applyMarkdown('x', 0, 1, 'italic')).toEqual({
      value: '*x*',
      selectionStart: 1,
      selectionEnd: 2,
    });
  });

  it('builds a link and selects the url placeholder', () => {
    expect(applyMarkdown('click', 0, 5, 'link')).toEqual({
      value: '[click](url)',
      selectionStart: 8,
      selectionEnd: 11,
    });
  });

  it('prefixes the current line with a quote marker', () => {
    expect(applyMarkdown('hello', 0, 5, 'quote')).toEqual({
      value: '> hello',
      selectionStart: 0,
      selectionEnd: 7,
    });
  });

  it('prefixes every selected line for a multiline quote', () => {
    expect(applyMarkdown('a\nb', 0, 3, 'quote')).toEqual({
      value: '> a\n> b',
      selectionStart: 0,
      selectionEnd: 7,
    });
  });

  it('clamps out-of-range indices', () => {
    expect(applyMarkdown('abc', 99, 99, 'bold')).toEqual({
      value: 'abc****',
      selectionStart: 5,
      selectionEnd: 5,
    });
  });

  it('returns the input unchanged for an unknown format', () => {
    expect(applyMarkdown('abc', 0, 1, 'nope')).toEqual({
      value: 'abc',
      selectionStart: 0,
      selectionEnd: 1,
    });
  });
});
