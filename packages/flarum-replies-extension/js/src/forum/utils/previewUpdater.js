/**
 * Drive live TextFormatter previews while tolerating Flarum's asynchronous
 * XSLT WebAssembly polyfill initialization (`window.xsltPolyfillReady`).
 *
 * Core's `ComposerPostPreview` latches `preview = content` before calling
 * `s9e.TextFormatter.preview`, so if `preview()` throws while the polyfill WASM
 * is still compiling, subsequent ticks skip rendering until the draft changes.
 */
export function createPreviewUpdater({ isVisible, getContent, render, getPolyfillReady }) {
  let lastRendered;
  let waitingForPolyfill = false;
  let polyfillSettled = false;
  let polyfillFailed = false;
  let disposed = false;

  function update() {
    if (disposed || waitingForPolyfill || polyfillFailed || !isVisible()) return;

    const content = getContent();
    if (lastRendered === content) return;

    try {
      render(content || '');
      lastRendered = content;
    } catch (error) {
      const readyFn = typeof getPolyfillReady === 'function' ? getPolyfillReady() : null;

      if (!polyfillSettled && typeof readyFn === 'function') {
        waitingForPolyfill = true;
        Promise.resolve()
          .then(() => readyFn())
          .then(
            () => {
              waitingForPolyfill = false;
              polyfillSettled = true;
              if (!disposed) update();
            },
            () => {
              waitingForPolyfill = false;
              polyfillSettled = true;
              polyfillFailed = true;
            }
          );
      } else {
        // Avoid spinning on a non-polyfill error; future content edits still retry.
        lastRendered = content;
      }
    }
  }

  function dispose() {
    disposed = true;
  }

  return { update, dispose };
}
