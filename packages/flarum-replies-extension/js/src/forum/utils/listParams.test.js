import { describe, it, expect } from 'vitest';
import { withFirstPostInclude } from './listParams';

describe('withFirstPostInclude', () => {
  it('adds firstPost to the include list', () => {
    expect(withFirstPostInclude({ include: ['user'] })).toEqual({ include: ['user', 'firstPost'] });
  });

  it('is idempotent when firstPost is already present', () => {
    expect(withFirstPostInclude({ include: ['user', 'firstPost'] })).toEqual({ include: ['user', 'firstPost'] });
  });

  it('creates the include list when missing', () => {
    expect(withFirstPostInclude({})).toEqual({ include: ['firstPost'] });
  });

  it('keeps other params intact', () => {
    const out = withFirstPostInclude({ include: ['user'], filter: { q: 'x' }, sort: '-createdAt' });
    expect(out.filter).toEqual({ q: 'x' });
    expect(out.sort).toBe('-createdAt');
  });

  it('does not mutate its input', () => {
    const input = { include: ['user'] };
    withFirstPostInclude(input);
    expect(input.include).toEqual(['user']);
  });
});
