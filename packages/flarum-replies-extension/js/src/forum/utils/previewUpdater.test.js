import { describe, it, expect, vi } from 'vitest';
import { createPreviewUpdater } from './previewUpdater';

function flushMicrotasks() {
  return new Promise((resolve) => setTimeout(resolve, 0));
}

describe('createPreviewUpdater', () => {
  it('renders immediately on first call and skips redundant renders when content is unchanged', () => {
    const render = vi.fn();
    const updater = createPreviewUpdater({
      isVisible: () => true,
      getContent: () => '**hello**',
      render,
      getPolyfillReady: () => null,
    });

    updater.update();
    updater.update();

    expect(render).toHaveBeenCalledTimes(1);
    expect(render).toHaveBeenCalledWith('**hello**');
  });

  it('re-renders when content changes and coerces falsy content to an empty string', () => {
    let draft = '';
    const render = vi.fn();
    const updater = createPreviewUpdater({
      isVisible: () => true,
      getContent: () => draft,
      render,
      getPolyfillReady: () => null,
    });

    updater.update();
    draft = 'updated';
    updater.update();

    expect(render).toHaveBeenCalledTimes(2);
    expect(render).toHaveBeenNthCalledWith(1, '');
    expect(render).toHaveBeenNthCalledWith(2, 'updated');
  });

  it('skips rendering when isVisible() returns false', () => {
    let visible = false;
    const render = vi.fn();
    const updater = createPreviewUpdater({
      isVisible: () => visible,
      getContent: () => 'draft',
      render,
      getPolyfillReady: () => null,
    });

    updater.update();
    expect(render).not.toHaveBeenCalled();

    visible = true;
    updater.update();
    expect(render).toHaveBeenCalledTimes(1);
    expect(render).toHaveBeenCalledWith('draft');
  });

  it('recovers automatically when render throws before xsltPolyfillReady resolves', async () => {
    let polyfillReady = false;
    let resolvePolyfill;
    const readyPromise = new Promise((resolve) => {
      resolvePolyfill = () => {
        polyfillReady = true;
        resolve();
      };
    });

    const render = vi.fn((content) => {
      if (!polyfillReady) {
        throw new Error('Polyfill XSLT Wasm module not yet loaded.');
      }
      return content;
    });

    const updater = createPreviewUpdater({
      isVisible: () => true,
      getContent: () => '**مرحبا**',
      render,
      getPolyfillReady: () => () => readyPromise,
    });

    updater.update();
    // Subsequent ticks while waiting must not spam render().
    updater.update();
    expect(render).toHaveBeenCalledTimes(1);

    resolvePolyfill();
    await flushMicrotasks();

    expect(render).toHaveBeenCalledTimes(2);
    expect(render).toHaveBeenLastCalledWith('**مرحبا**');

    // Once rendered, unchanged ticks stay cached.
    updater.update();
    expect(render).toHaveBeenCalledTimes(2);
  });

  it('does not render after dispose() if xsltPolyfillReady resolves after unmount', async () => {
    let resolvePolyfill;
    const readyPromise = new Promise((resolve) => {
      resolvePolyfill = resolve;
    });

    const render = vi.fn(() => {
      throw new Error('Polyfill XSLT Wasm module not yet loaded.');
    });

    const updater = createPreviewUpdater({
      isVisible: () => true,
      getContent: () => 'draft',
      render,
      getPolyfillReady: () => () => readyPromise,
    });

    updater.update();
    expect(render).toHaveBeenCalledTimes(1);

    updater.dispose();
    resolvePolyfill();
    await flushMicrotasks();

    updater.update();
    expect(render).toHaveBeenCalledTimes(1);
  });

  it('stops retrying cleanly when xsltPolyfillReady rejects permanently', async () => {
    let rejectPolyfill;
    const readyPromise = new Promise((_, reject) => {
      rejectPolyfill = reject;
    });

    let draft = 'first';
    const render = vi.fn(() => {
      throw new Error('Wasm blocked by CSP');
    });

    const updater = createPreviewUpdater({
      isVisible: () => true,
      getContent: () => draft,
      render,
      getPolyfillReady: () => () => readyPromise,
    });

    updater.update();
    expect(render).toHaveBeenCalledTimes(1);

    rejectPolyfill(new Error('CSP CompileError'));
    await flushMicrotasks();

    draft = 'second';
    updater.update();
    expect(render).toHaveBeenCalledTimes(1);
  });

  it('does not infinitely loop if render throws after polyfill settled, and retries on future edits', async () => {
    let draft = 'bad';
    const render = vi.fn((content) => {
      if (content === 'bad') {
        throw new Error('Formatter error');
      }
    });

    const updater = createPreviewUpdater({
      isVisible: () => true,
      getContent: () => draft,
      render,
      getPolyfillReady: () => () => Promise.resolve(),
    });

    updater.update();
    await flushMicrotasks();
    // Initial attempt + retry once polyfill settled.
    expect(render).toHaveBeenCalledTimes(2);

    // Same broken content does not spin.
    updater.update();
    expect(render).toHaveBeenCalledTimes(2);

    // Editing content retries cleanly.
    draft = 'good';
    updater.update();
    expect(render).toHaveBeenCalledTimes(3);
    expect(render).toHaveBeenLastCalledWith('good');
  });
});
