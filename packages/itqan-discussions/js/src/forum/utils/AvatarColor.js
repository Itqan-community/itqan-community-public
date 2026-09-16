import app from 'flarum/forum/app';
import { getVisualDepth } from '../components/CommentTree';

/**
 * Avatar dominant color extraction utility.
 * Extracts dominant color from user avatars (img or span) to color nested thread reply lines.
 */

export const colorCache = new Map();
export const postColorCache = new Map();

// Fallback palette inspired by vibrant modern UI colors
const FALLBACK_PALETTE = [
  '#0D9488', // Teal
  '#F97316', // Orange
  '#3B82F6', // Blue
  '#8B5CF6', // Purple
  '#EC4899', // Pink
  '#10B981', // Emerald
  '#EF4444', // Red
  '#F59E0B', // Amber
  '#6366F1', // Indigo
];

/**
 * Deterministically pick a color from the palette based on a string (username/ID).
 */
export function getHashColor(str) {
  if (!str) return FALLBACK_PALETTE[0];
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = (hash << 5) - hash + str.charCodeAt(i);
    hash |= 0;
  }
  const index = Math.abs(hash) % FALLBACK_PALETTE.length;
  return FALLBACK_PALETTE[index];
}

/**
 * Extract dominant RGB color from an HTMLImageElement using an offscreen canvas.
 */
function extractFromImg(img) {
  try {
    if (!img.complete || img.naturalWidth === 0) {
      return null;
    }

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) return null;

    canvas.width = 16;
    canvas.height = 16;
    ctx.drawImage(img, 0, 0, 16, 16);

    let imageData;
    try {
      imageData = ctx.getImageData(0, 0, 16, 16).data;
    } catch (corsErr) {
      return null;
    }

    let rTotal = 0, gTotal = 0, bTotal = 0, count = 0;
    let fallbackR = 0, fallbackG = 0, fallbackB = 0, fallbackCount = 0;

    for (let i = 0; i < imageData.length; i += 4) {
      const a = imageData[i + 3];
      if (a < 128) continue; // Skip transparent pixels

      const r = imageData[i];
      const g = imageData[i + 1];
      const b = imageData[i + 2];

      fallbackR += r;
      fallbackG += g;
      fallbackB += b;
      fallbackCount++;

      // Skip near-white and near-black for dominant color extraction
      if (r > 240 && g > 240 && b > 240) continue;
      if (r < 20 && g < 20 && b < 20) continue;

      rTotal += r;
      gTotal += g;
      bTotal += b;
      count++;
    }

    if (count > 0) {
      const rAvg = Math.round(rTotal / count);
      const gAvg = Math.round(gTotal / count);
      const bAvg = Math.round(bTotal / count);
      return `rgb(${rAvg}, ${gAvg}, ${bAvg})`;
    }

    if (fallbackCount > 0) {
      const rAvg = Math.round(fallbackR / fallbackCount);
      const gAvg = Math.round(fallbackG / fallbackCount);
      const bAvg = Math.round(fallbackB / fallbackCount);
      return `rgb(${rAvg}, ${gAvg}, ${bAvg})`;
    }

    return null;
  } catch (err) {
    return null;
  }
}

/**
 * Get or extract the dominant avatar color for a given DOM element or user identifier.
 */
export function getAvatarDominantColor(avatarEl, fallbackKey = '', onColorReady = null) {
  if (!avatarEl) {
    return getHashColor(fallbackKey);
  }

  // 1. If it's a span/div with inline style background-color (Flarum letter avatar)
  if (avatarEl.tagName === 'SPAN' || avatarEl.tagName === 'DIV') {
    const bg = avatarEl.style.backgroundColor || window.getComputedStyle(avatarEl).backgroundColor;
    if (bg && bg !== 'transparent' && bg !== 'rgba(0, 0, 0, 0)') {
      return bg;
    }
  }

  const img = avatarEl.tagName === 'IMG' ? avatarEl : avatarEl.querySelector('img');
  if (!img) {
    const bg = avatarEl.style.backgroundColor || window.getComputedStyle(avatarEl).backgroundColor;
    if (bg && bg !== 'transparent' && bg !== 'rgba(0, 0, 0, 0)') {
      return bg;
    }
    return getHashColor(fallbackKey);
  }

  const src = img.src || img.getAttribute('src');
  if (!src) {
    return getHashColor(fallbackKey);
  }

  if (colorCache.has(src)) {
    return colorCache.get(src);
  }

  if (img.complete && img.naturalWidth > 0) {
    const extracted = extractFromImg(img);
    if (extracted) {
      colorCache.set(src, extracted);
      return extracted;
    }
    return getHashColor(fallbackKey);
  }

  if (onColorReady) {
    img.addEventListener(
      'load',
      () => {
        const extracted = extractFromImg(img);
        if (extracted) {
          colorCache.set(src, extracted);
          onColorReady(extracted);
        }
      },
      { once: true }
    );
  }

  return getHashColor(fallbackKey);
}

/**
 * Update the `--rail-color` CSS variable on all DOM rails belonging to a specific ancestor.
 */
export function updateRailsForAncestor(ancestorId, color) {
  if (!ancestorId || !color) return;
  const rails = document.querySelectorAll(`.itqan-thread-rail[data-rail-ancestor-id="${ancestorId}"]`);
  rails.forEach((rail) => {
    rail.style.setProperty('--rail-color', color);
  });
}

/**
 * Get the dominant color associated with a post's author.
 */
export function getPostColor(post) {
  if (!post) return FALLBACK_PALETTE[0];
  const postId = typeof post.id === 'function' ? String(post.id()) : String(post.id || '');
  if (postColorCache.has(postId)) {
    return postColorCache.get(postId);
  }

  const user = typeof post.user === 'function' ? post.user() : null;
  const username = user && typeof user.displayName === 'function' ? user.displayName() : (user && user.username ? user.username() : postId);
  const avatarUrl = user && typeof user.avatarUrl === 'function' ? user.avatarUrl() : null;

  if (avatarUrl && colorCache.has(avatarUrl)) {
    const color = colorCache.get(avatarUrl);
    postColorCache.set(postId, color);
    return color;
  }

  const el = document.querySelector(`.PostStream-item[data-id="${postId}"]`);
  const avatarEl = el ? el.querySelector('.PostUser-avatar, .Avatar') : null;
  if (avatarEl) {
    const color = getAvatarDominantColor(avatarEl, username, (readyColor) => {
      postColorCache.set(postId, readyColor);
      updateRailsForAncestor(postId, readyColor);
    });
    if (color && !color.startsWith('#')) {
      postColorCache.set(postId, color);
      return color;
    }
  }

  // Fallback hash color for initial render (not stored permanently in cache so DOM inspection can upgrade it)
  return getHashColor(username);
}

/**
 * Immediate-parent rail only (GitHub-style muted lineage). Multi-ancestor
 * rainbow rails were dropped — they seamed and stole measure on phones.
 *
 * Column uses *visual* depth (viewport-clamped), not the raw server depth, so
 * the painted line stays on the same indent token as `data-thread-depth`.
 */
export function getPostRails(post) {
  if (!post) return [];

  const parentId = typeof post.parentId === 'function' ? post.parentId() : null;
  if (!parentId) return [];

  const parent = app.store ? app.store.getById('posts', String(parentId)) : null;
  if (!parent) return [];
  if (typeof parent.number === 'function' && parent.number() === 1) return [];

  const visualDepth = getVisualDepth(post);
  if (visualDepth < 1) return [];

  // Parent sits one visual step above this reply.
  const col = Math.max(0, visualDepth - 1);
  // Depth ≥2 parents use --avatar-sm; rail centre must match.
  const deep = col >= 2;

  return [
    {
      col,
      deep,
      postId: String(parent.id()),
      color: null,
    },
  ];
}

