/**
 * Avatar dominant color extraction utility.
 * Extracts dominant color from user avatars (img or span) to color nested thread reply lines.
 */

const colorCache = new Map();

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

    // Downscale for fast sampling
    canvas.width = 16;
    canvas.height = 16;
    ctx.drawImage(img, 0, 0, 16, 16);

    const imageData = ctx.getImageData(0, 0, 16, 16).data;
    let rTotal = 0, gTotal = 0, bTotal = 0, count = 0;

    for (let i = 0; i < imageData.length; i += 4) {
      const a = imageData[i + 3];
      if (a < 128) continue; // Skip transparent pixels

      const r = imageData[i];
      const g = imageData[i + 1];
      const b = imageData[i + 2];

      // Skip near-white (backgrounds) and near-black
      if (r > 240 && g > 240 && b > 240) continue;
      if (r < 20 && g < 20 && b < 20) continue;

      rTotal += r;
      gTotal += g;
      bTotal += b;
      count++;
    }

    if (count === 0) {
      // Sample center pixel
      const centerIdx = (8 * 16 + 8) * 4;
      const r = imageData[centerIdx];
      const g = imageData[centerIdx + 1];
      const b = imageData[centerIdx + 2];
      return `rgb(${r}, ${g}, ${b})`;
    }

    const rAvg = Math.round(rTotal / count);
    const gAvg = Math.round(gTotal / count);
    const bAvg = Math.round(bTotal / count);
    return `rgb(${rAvg}, ${gAvg}, ${bAvg})`;
  } catch (err) {
    // Canvas security error (e.g. cross-origin taint)
    return null;
  }
}

/**
 * Get or extract the dominant avatar color for a given DOM element or user identifier.
 * @param {HTMLElement} avatarEl - The avatar element (.Avatar, .PostUser-avatar, or img/span)
 * @param {string} fallbackKey - Key for hash fallback (e.g. username or userId)
 * @param {Function} onColorReady - Callback if color extraction was async (image loading)
 * @returns {string} CSS color string (hex or rgb)
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

  // Find img inside or check if element itself is img
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
    const color = extractFromImg(img) || getHashColor(fallbackKey);
    colorCache.set(src, color);
    return color;
  }

  // Image not yet loaded — register listener
  if (onColorReady) {
    img.addEventListener(
      'load',
      () => {
        const color = extractFromImg(img) || getHashColor(fallbackKey);
        colorCache.set(src, color);
        onColorReady(color);
      },
      { once: true }
    );
  }

  return getHashColor(fallbackKey);
}
