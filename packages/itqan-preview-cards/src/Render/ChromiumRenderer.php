<?php

namespace Itqan\PreviewCards\Render;

use Flarum\Foundation\Paths;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ChromiumRenderer implements CardRenderer
{
    private const PNG_MAGIC = "\x89PNG\r\n\x1a\n";

    public function __construct(
        protected Paths $paths,
        protected LoggerInterface $logger,
    ) {
    }

    public static function binary(): ?string
    {
        $candidates = array_filter([
            getenv('CHROME_BIN') ?: null,
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/local/bin/chromium',
        ]);

        foreach ($candidates as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $found = trim((string) @shell_exec('command -v chromium 2>/dev/null'));

        return $found !== '' && is_executable($found) ? $found : null;
    }

    public function available(): bool
    {
        return self::binary() !== null;
    }

    public function render(string $html, int $width, int $height): string
    {
        $binary = self::binary();

        if ($binary === null) {
            throw new RuntimeException('Chromium binary not found. Set CHROME_BIN or install chromium.');
        }

        $directory = $this->paths->storage.'/tmp/preview-cards/'.bin2hex(random_bytes(8));

        if (! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create render directory: {$directory}");
        }

        try {
            file_put_contents($directory.'/card.html', $html);

            $output = $directory.'/card.png';

            $command = [
                'timeout', '20',
                $binary,
                '--headless=new',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-dev-shm-usage',
                '--hide-scrollbars',
                '--force-device-scale-factor=1',
                '--disable-lcd-text',
                '--font-render-hinting=none',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-extensions',
                '--disable-background-networking',
                '--disable-sync',
                '--disable-translate',
                '--host-resolver-rules=MAP * ~NOTFOUND',
                '--window-size='.$width.','.$height,
                '--virtual-time-budget=5000',
                '--run-all-compositor-stages-before-draw',
                '--user-data-dir='.$directory.'/profile',
                '--screenshot='.$output,
                'file://'.$directory.'/card.html',
            ];

            $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

            $process = proc_open($command, $descriptors, $pipes, $directory, [
                'HOME' => $directory,
                'PATH' => getenv('PATH') ?: '/usr/bin:/bin',
            ]);

            if (! is_resource($process)) {
                throw new RuntimeException('Unable to start Chromium.');
            }

            $stderr = (string) stream_get_contents($pipes[2]);
            stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            if ($exitCode !== 0 || ! is_file($output)) {
                throw new RuntimeException('Chromium render failed (exit '.$exitCode.'): '.substr($stderr, -500));
            }

            $png = (string) file_get_contents($output);

            if (! str_starts_with($png, self::PNG_MAGIC)) {
                throw new RuntimeException('Chromium did not produce a PNG.');
            }

            return $png;
        } finally {
            $this->removeDirectory($directory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (basename($file) === '.' || basename($file) === '..') {
                continue;
            }

            is_dir($file) ? $this->removeDirectory($file) : @unlink($file);
        }

        @rmdir($directory);
    }
}
