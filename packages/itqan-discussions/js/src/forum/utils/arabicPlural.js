/**
 * Arabic pluralization (تفقيط) for the discussion metadata strip.
 *
 * Mirrors the CLDR Arabic categories: zero, one, two, few (n%100 3..10),
 * many (n%100 11..99) and other. `#` is replaced with the number.
 */

const NOUNS = {
  participants: { zero: 'لا مشاركون', one: 'مشارك واحد', two: 'مشاركان', few: '# مشاركون', many: '# مشاركاً', other: '# مشارك' },
  views: { zero: 'لا مشاهدات', one: 'مشاهدة واحدة', two: 'مشاهدتان', few: '# مشاهدات', many: '# مشاهدة', other: '# مشاهدة' },
  replies: { zero: 'لا ردود', one: 'رد واحد', two: 'ردان', few: '# ردود', many: '# رداً', other: '# رد' },
  comments: { zero: 'لا تعليقات', one: 'تعليق واحد', two: 'تعليقان', few: '# تعليقات', many: '# تعليقاً', other: '# تعليق' },
  discussions: { zero: 'لا نقاشات', one: 'نقاش واحد', two: 'نقاشان', few: '# نقاشات', many: '# نقاشاً', other: '# نقاش' },
};

const ORDER = ['zero', 'one', 'two', 'few', 'many', 'other'];

function category(n) {
  if (n === 0) return 0;
  if (n === 1) return 1;
  if (n === 2) return 2;
  const mod100 = n % 100;
  if (mod100 >= 3 && mod100 <= 10) return 3;
  if (mod100 >= 11 && mod100 <= 99) return 4;
  return 5;
}

export function formatArabicPlural(count, type = 'comments') {
  const n = Number(count) || 0;
  const noun = NOUNS[type] || NOUNS.comments;
  return noun[ORDER[category(n)]].replace('#', String(n));
}

export default formatArabicPlural;
