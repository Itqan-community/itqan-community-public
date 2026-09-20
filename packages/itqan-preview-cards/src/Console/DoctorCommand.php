<?php

namespace Itqan\PreviewCards\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Settings\SettingsRepositoryInterface;
use Itqan\PreviewCards\Cache\CardCache;
use Itqan\PreviewCards\Render\CardRenderer;
use Itqan\PreviewCards\Render\FontSet;
use Itqan\PreviewCards\Settings;

class DoctorCommand extends AbstractCommand
{
    public function __construct(
        protected Settings $settings,
        protected SettingsRepositoryInterface $repository,
        protected CardRenderer $renderer,
        protected FontSet $fonts,
        protected CardCache $cache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('itqan-cards:doctor')
            ->setDescription('Report on the preview card pipeline: renderer, fonts, cache and settings');
    }

    protected function fire(): int
    {
        $problems = 0;

        $enabled = $this->settings->enabled();
        $this->info('Cards enabled: '.($enabled ? 'yes' : 'no'));

        $binary = \Itqan\PreviewCards\Render\ChromiumRenderer::binary();

        if ($binary !== null) {
            $version = trim((string) @shell_exec(escapeshellarg($binary).' --version 2>/dev/null'));
            $this->info('Chromium: '.$binary.($version !== '' ? " ({$version})" : ''));
        } else {
            $this->error('Chromium: not found. Set CHROME_BIN or install chromium; renders will serve the brand card.');
            $problems++;
        }

        $this->info('Font fingerprint: '.$this->fonts->fingerprint());

        $sources = $this->fonts->sources();
        $paths = $this->fonts->paths();

        foreach ($this->fonts->faceFilenames() as $file) {
            if (isset($paths[$file])) {
                $this->info('  font ok: '.$file.' ← '.$sources[$file].' ('.number_format(filesize($paths[$file]) / 1024, 1).' KB)');
            } else {
                $this->error('  font missing: '.$file);
                $problems++;
            }
        }

        $stats = $this->cache->stats();
        $this->info("Cache: {$stats['files']} file(s), ".number_format($stats['bytes'] / 1024, 1).' KB');

        $this->info('Language policy: '.$this->settings->languagePolicy().' (fallback '.$this->settings->fallbackLocale().')');
        $this->info('Render concurrency: '.$this->settings->renderConcurrency());
        $this->info('Provisional TTL: '.$this->settings->provisionalTtl().'s');
        $this->info('Prune retention: '.$this->settings->pruneDays().' day(s)');

        return $problems === 0 ? 0 : 1;
    }
}
