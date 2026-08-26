import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import extractText from 'flarum/common/utils/extractText';

const MIN_SCALE = 1;
const MAX_SCALE = 6;
const DOUBLE_TAP_SCALE = 2.5;
const DOUBLE_TAP_MS = 300;
const DISMISS_DISTANCE = 110;

/**
 * A fullscreen viewer for images in posts.
 *
 * Written here rather than pulled from a library: the whole behaviour is a few
 * hundred lines of pointer maths, and a lightbox dependency would add an npm
 * package and its weight to a bundle that currently has neither.
 *
 * The transform is kept as `scale` plus a translation rather than as CSS
 * `zoom` or width changes, so every gesture is one composited property and
 * nothing reflows while a finger is down.
 */
export default class ImageLightbox extends Component {
  oninit(vnode) {
    super.oninit(vnode);

    this.scale = 1;
    this.x = 0;
    this.y = 0;

    // Gesture bookkeeping. `start` holds the transform as it was when the
    // current gesture began, so each move is computed from a fixed origin
    // instead of accumulating rounding error.
    this.pointers = new Map();
    this.start = null;
    this.lastTap = 0;
    this.dismissing = 0;
  }

  oncreate(vnode) {
    super.oncreate(vnode);

    this.stage = this.element.querySelector('.ImageLightbox-stage');
    this.figure = this.element.querySelector('.ImageLightbox-image');

    // A keydown listener on the overlay only fires while something inside it
    // has focus; on the document it also catches the moment before focus has
    // settled.
    this.onKeyDown = this.keydown.bind(this);
    document.addEventListener('keydown', this.onKeyDown);

    this.element.focus();
  }

  onremove() {
    document.removeEventListener('keydown', this.onKeyDown);
  }

  view() {
    const { src, alt } = this.attrs;
    const opacity = 1 - Math.min(this.dismissing / (DISMISS_DISTANCE * 2), 0.7);

    return (
      <div
        className={'ImageLightbox' + (this.scale > 1 ? ' ImageLightbox--zoomed' : '')}
        role="dialog"
        aria-modal="true"
        aria-label={extractText(app.translator.trans('itqan-theme.forum.lightbox.title'))}
        tabindex="-1"
        // Set as a plain background rather than a custom property: Mithril
        // assigns style objects with `style[key] = value`, which silently does
        // nothing for `--custom` names, so the fade never reached the page.
        style={this.dismissing ? { backgroundColor: `rgba(0, 0, 0, ${(0.92 * opacity).toFixed(3)})` } : {}}
        onclick={(e) => {
          // Only a click on the backdrop itself closes; one that lands on the
          // image is the start of a gesture.
          if (e.target === e.currentTarget || e.target.classList.contains('ImageLightbox-stage')) this.attrs.onclose();
        }}
      >
        <div className="ImageLightbox-controls">
          <Button
            className="Button Button--icon ImageLightbox-close"
            icon="fas fa-times"
            aria-label={extractText(app.translator.trans('itqan-theme.forum.lightbox.close'))}
            onclick={() => this.attrs.onclose()}
          />
        </div>

        <div
          className="ImageLightbox-stage"
          onpointerdown={this.pointerDown.bind(this)}
          onpointermove={this.pointerMove.bind(this)}
          onpointerup={this.pointerUp.bind(this)}
          onpointercancel={this.pointerUp.bind(this)}
          onwheel={this.wheel.bind(this)}
        >
          <img
            className="ImageLightbox-image"
            src={src}
            alt={alt || ''}
            draggable={false}
            style={{ transform: `translate3d(${this.x}px, ${this.y + this.dismissing}px, 0) scale(${this.scale})` }}
          />
        </div>

        <div className="ImageLightbox-hint">
          {app.translator.trans('itqan-theme.forum.lightbox.hint')}
        </div>
      </div>
    );
  }

  keydown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      this.attrs.onclose();
      return;
    }

    // The overlay holds a single control, so trapping focus is a matter of
    // keeping Tab on it rather than cycling a list.
    if (e.key === 'Tab') {
      e.preventDefault();
      this.element.querySelector('.ImageLightbox-close')?.focus();
    }
  }

  // ---------------------------------------------------------------------
  // Gestures

  stageRect() {
    return this.stage.getBoundingClientRect();
  }

  /**
   * Keeps the image from being dragged off into empty space: at any scale it
   * may only travel as far as the part of it that is actually overflowing.
   */
  clamp() {
    const rect = this.figure.getBoundingClientRect();
    const stage = this.stageRect();

    // The rendered size at scale 1, recovered from the current scale rather
    // than measured — measuring mid-gesture would read the transformed box.
    const baseWidth = rect.width / this.scale;
    const baseHeight = rect.height / this.scale;

    const overflowX = Math.max(0, (baseWidth * this.scale - stage.width) / 2);
    const overflowY = Math.max(0, (baseHeight * this.scale - stage.height) / 2);

    this.x = Math.min(overflowX, Math.max(-overflowX, this.x));
    this.y = Math.min(overflowY, Math.max(-overflowY, this.y));
  }

  /**
   * Scales about a point, so the pixel under the fingers (or the cursor)
   * stays under them.
   */
  zoomTo(scale, originX, originY) {
    const next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, scale));
    const ratio = next / this.scale;
    const stage = this.stageRect();
    const cx = originX - stage.left - stage.width / 2;
    const cy = originY - stage.top - stage.height / 2;

    this.x = cx - (cx - this.x) * ratio;
    this.y = cy - (cy - this.y) * ratio;
    this.scale = next;

    if (this.scale === MIN_SCALE) {
      this.x = 0;
      this.y = 0;
    } else {
      this.clamp();
    }
  }

  pointerDown(e) {
    this.stage.setPointerCapture?.(e.pointerId);
    this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

    if (this.pointers.size === 1) {
      const now = Date.now();

      if (now - this.lastTap < DOUBLE_TAP_MS) {
        this.zoomTo(this.scale > 1 ? MIN_SCALE : DOUBLE_TAP_SCALE, e.clientX, e.clientY);
        this.lastTap = 0;
        m.redraw();
        return;
      }

      this.lastTap = now;
    }

    this.beginGesture();
  }

  beginGesture() {
    // `Array.from`, not a spread. Flarum's Babel config transforms `[...x]`
    // with a helper that only understands arrays and array-likes, so
    // spreading a Map iterator yields a one-element array holding the
    // iterator itself — every gesture then looks like a single finger.
    const points = Array.from(this.pointers.values());

    this.start = {
      scale: this.scale,
      x: this.x,
      y: this.y,
      distance: points.length > 1 ? this.distance(points[0], points[1]) : 0,
      centre: this.centre(points),
      dismissing: this.dismissing,
    };
  }

  distance(a, b) {
    return Math.hypot(a.x - b.x, a.y - b.y);
  }

  centre(points) {
    if (!points.length) return { x: 0, y: 0 };

    return {
      x: points.reduce((t, p) => t + p.x, 0) / points.length,
      y: points.reduce((t, p) => t + p.y, 0) / points.length,
    };
  }

  pointerMove(e) {
    if (!this.pointers.has(e.pointerId)) return;

    this.pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });

    const points = Array.from(this.pointers.values());
    if (!this.start) return;

    e.preventDefault();

    if (points.length > 1) {
      // Pinch. The gesture's own midpoint is the anchor, which is what makes
      // it feel like the image is being stretched between the fingers rather
      // than scaled about its middle.
      const ratio = this.distance(points[0], points[1]) / (this.start.distance || 1);
      const centre = this.centre(points);
      const next = Math.min(MAX_SCALE, Math.max(MIN_SCALE, this.start.scale * ratio));
      const factor = next / this.start.scale;
      const stage = this.stageRect();
      const cx = centre.x - stage.left - stage.width / 2;
      const cy = centre.y - stage.top - stage.height / 2;

      this.scale = next;
      this.x = cx - (cx - this.start.x) * factor;
      this.y = cy - (cy - this.start.y) * factor;
      this.clamp();
    } else {
      const dx = points[0].x - this.start.centre.x;
      const dy = points[0].y - this.start.centre.y;

      if (this.scale > MIN_SCALE) {
        this.x = this.start.x + dx;
        this.y = this.start.y + dy;
        this.clamp();
      } else if (dy > 0) {
        // At natural size a downward drag is the close gesture, not a pan —
        // the same idiom as the OS photo viewers.
        this.dismissing = dy;
      }
    }

    m.redraw();
  }

  pointerUp(e) {
    this.pointers.delete(e.pointerId);

    if (this.dismissing > DISMISS_DISTANCE) {
      this.attrs.onclose();
      return;
    }

    this.dismissing = 0;

    if (this.pointers.size) {
      // A finger lifted mid-pinch: restart from the remaining one so the
      // image does not jump.
      this.beginGesture();
    } else {
      this.start = null;
    }

    m.redraw();
  }

  wheel(e) {
    e.preventDefault();
    this.zoomTo(this.scale * (e.deltaY < 0 ? 1.15 : 1 / 1.15), e.clientX, e.clientY);
    m.redraw();
  }
}
