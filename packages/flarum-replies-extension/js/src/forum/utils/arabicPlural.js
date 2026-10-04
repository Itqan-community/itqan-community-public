/**
 * Arabic pluralization formatter (تفقيط الأعداد للتعليقات والردود)
 * @param {number} count
 * @param {'comment'|'reply'} type
 * @returns {string}
 */
export function formatArabicPlural(count, type = 'comment') {
  const n = Number(count) || 0;

  if (type === 'reply') {
    if (n === 1) return 'رد إضافي واحد';
    if (n === 2) return 'ردان إضافيان';
    if (n >= 3 && n <= 10) return `${n} ردود إضافية`;
    if (n >= 11 && n <= 99) return `${n} رداً إضافياً`;
    return `${n} رد إضافي`;
  }

  // default: comment / تعليق
  if (n === 0) return 'لا يوجد تعليقات';
  if (n === 1) return 'تعليق واحد';
  if (n === 2) return 'تعليقان';
  if (n >= 3 && n <= 10) return `${n} تعليقات`;
  if (n >= 11 && n <= 99) return `${n} تعليقاً`;
  return `${n} تعليق`;
}
