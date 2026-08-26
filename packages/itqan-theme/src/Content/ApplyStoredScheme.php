<?php

namespace Itqan\Theme\Content;

use Flarum\Frontend\Document;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Writes the reader's colour scheme onto `<html>` before the browser paints.
 *
 * Without this the page loads in whichever scheme the stylesheet was compiled
 * for and only corrects itself once the frontend boots, which on a slow
 * connection is a full white page flashed at someone who asked for dark.
 *
 * Everything a signed-in reader needs is already known here, so their scheme is
 * inlined directly. Guests keep their choice in `localStorage`, which only the
 * browser can read — hence the small script rather than a plain attribute.
 */
class ApplyStoredScheme
{
    const MODES = ['light', 'dark', 'auto'];

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function __invoke(Document $document, Request $request)
    {
        $document->head[] = $this->script(
            $this->storedMode($request->getAttribute('actor')),
            $this->defaultMode()
        );
    }

    protected function defaultMode(): string
    {
        $configured = $this->settings->get('itqan-theme.default_mode');

        return in_array($configured, self::MODES, true) ? $configured : 'auto';
    }

    /**
     * @return string|null the signed-in reader's saved choice, if they have one
     */
    protected function storedMode($actor): ?string
    {
        if (! $actor instanceof User || ! $actor->exists) {
            return null;
        }

        $mode = $actor->getPreference('itqanTheme');

        return in_array($mode, self::MODES, true) ? $mode : null;
    }

    protected function script(?string $stored, string $default): string
    {
        $storedJson = json_encode($stored, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $defaultJson = json_encode($default, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
<script>
(function () {
    try {
        var account = $storedJson;
        var stored = null;
        try { stored = localStorage.getItem('itqan-theme'); } catch (e) {}

        var mode = account || stored || $defaultJson;

        if (['light', 'dark', 'auto'].indexOf(mode) === -1) mode = 'auto';

        if (mode === 'auto') {
            mode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        document.documentElement.setAttribute('data-itqan-theme', mode);
    } catch (e) {
        // Leave the document as the stylesheet compiled it; a wrong scheme is
        // recoverable once the frontend boots, a thrown error here is not.
    }
})();
</script>
HTML;
    }
}
