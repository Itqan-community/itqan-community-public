import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import extractText from 'flarum/common/utils/extractText';

import ImageLightbox from './components/ImageLightbox';

const MARKER = 'itqanLightbox';
const OPEN_CLASS = 'itqan-lightbox-open';

// Images that are decoration, navigation or already interactive. Emoji and
// avatars are obvious; an image the author wrapped in a link belongs to that
// link, and hijacking the tap would take the reader somewhere they did not ask
// to go.
const IGNORE = ['.emoji', '.Avatar', '.PostMention-avatar', '.fof-upload-file-icon', '[data-no-lightbox]'];

let host = null;
let current = null;
let restoreFocusTo = null;

function state() {
  return current;
}

function mount() {
  if (host) return;

  host = document.createElement('div');
  host.className = 'ImageLightbox-host';
  document.body.appendChild(host);

  m.mount(host, {
    view: () =>
      state() ? <ImageLightbox src={state().src} alt={state().alt} onclose={close} /> : null,
  });
}

function open(image) {
  mount();

  restoreFocusTo = image;
  current = {
    // The full-size source when the theme has scaled the image down for the
    // post; `src` is what the reader is already looking at otherwise.
    src: image.dataset.fullSrc || image.currentSrc || image.src,
    alt: image.getAttribute('alt') || '',
  };

  // The page behind must not scroll while a finger is panning the image.
  document.documentElement.classList.add(OPEN_CLASS);

  m.redraw();
}

function close() {
  current = null;
  document.documentElement.classList.remove(OPEN_CLASS);

  // Hand focus back to the image that opened it, so a keyboard reader carries
  // on from where they were rather than at the top of the document.
  if (restoreFocusTo && restoreFocusTo.isConnected) restoreFocusTo.focus();
  restoreFocusTo = null;

  m.redraw();
}

function eligible(image) {
  if (!image || image.tagName !== 'IMG') return false;
  if (image.closest('a')) return false;
  if (IGNORE.some((selector) => image.matches(selector) || image.closest(selector))) return false;

  return !!image.closest('.Post-body');
}

/**
 * Gives the images in a post the attributes that make them reachable without a
 * mouse. Opening does not depend on this — the click handler tests each image
 * as it is clicked — so an image the post stream renders in a way this never
 * sees is still openable, just not tabbable.
 */
function markImages(element) {
  if (!element) return;

  element.querySelectorAll('.Post-body img').forEach((image) => {
    if (image.dataset[MARKER] || !eligible(image)) return;

    image.dataset[MARKER] = '1';
    image.setAttribute('tabindex', '0');
    image.setAttribute('role', 'button');
    image.setAttribute('aria-label', extractText(app.translator.trans('itqan-theme.forum.lightbox.open')));
  });
}

export default function addImageLightbox() {
  extend(CommentPost.prototype, 'refreshContent', function () {
    markImages(this.element);
  });

  // One listener for the whole document rather than one per image: the post
  // stream replaces its contents as the reader scrolls, and handlers bound to
  // elements would have to be rebound with them.
  //
  // Eligibility is tested here, at the moment of the click, rather than read
  // from an attribute stamped on earlier — so this works on any image in a
  // post body regardless of how it got there.
  document.addEventListener('click', (e) => {
    const image = e.target.closest?.('img');

    if (!eligible(image)) return;

    e.preventDefault();
    open(image);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    if (!eligible(e.target)) return;

    e.preventDefault();
    open(e.target);
  });
}
