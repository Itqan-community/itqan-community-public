<?php

namespace Itqan\PreviewCards\Cache;

use Flarum\Foundation\Paths;
use RuntimeException;

class CardCache
{
    public function __construct(protected Paths $paths)
    {
    }

    public function root(): string
    {
        return $this->paths->storage.'/preview-cards';
    }

    public function path(int $discussionId, string $hash): string
    {
        return $this->root().'/'.$discussionId.'/'.$hash.'.png';
    }

    public function has(int $discussionId, string $hash): bool
    {
        return is_file($this->path($discussionId, $hash));
    }

    public function read(int $discussionId, string $hash): ?string
    {
        $path = $this->path($discussionId, $hash);

        if (! is_file($path)) {
            return null;
        }

        $bytes = file_get_contents($path);

        return $bytes === false ? null : $bytes;
    }

    public function store(int $discussionId, string $hash, string $png): void
    {
        $this->write($this->path($discussionId, $hash), $png);
    }

    public function brandPath(string $locale): string
    {
        return $this->root().'/brand/brand-'.$locale.'.png';
    }

    public function brand(string $locale): ?string
    {
        $path = $this->brandPath($locale);

        if (! is_file($path)) {
            return null;
        }

        $bytes = file_get_contents($path);

        return $bytes === false ? null : $bytes;
    }

    public function storeBrand(string $locale, string $png): void
    {
        $this->write($this->brandPath($locale), $png);
    }

    public function deleteDiscussion(int $discussionId): void
    {
        $this->removeDirectory($this->root().'/'.$discussionId);
    }

    public function prune(int $days): int
    {
        $cutoff = time() - ($days * 86400);
        $removed = 0;

        foreach (glob($this->root().'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $basename = basename($directory);

            if ($basename === 'brand' || $basename === 'locks') {
                continue;
            }

            foreach (glob($directory.'/*.png') ?: [] as $file) {
                if (filemtime($file) < $cutoff) {
                    @unlink($file);
                    $removed++;
                }
            }

            if (empty(glob($directory.'/*'))) {
                @rmdir($directory);
            }
        }

        return $removed;
    }

    public function clearGenerated(): array
    {
        $files = 0;
        $bytes = 0;

        foreach (glob($this->root().'/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $basename = basename($directory);

            if ($basename === 'brand' || $basename === 'locks') {
                continue;
            }

            foreach (glob($directory.'/*') ?: [] as $file) {
                if (is_file($file)) {
                    $files++;
                    $bytes += (int) filesize($file);
                }
            }

            $this->removeDirectory($directory);
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    public function stats(): array
    {
        $files = 0;
        $bytes = 0;

        foreach (glob($this->root().'/*/*.png') ?: [] as $file) {
            $files++;
            $bytes += (int) filesize($file);
        }

        return ['files' => $files, 'bytes' => $bytes];
    }

    private function write(string $path, string $png): void
    {
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create card directory: {$directory}");
        }

        $tmp = $path.'.tmp.'.getmypid().'.'.bin2hex(random_bytes(4));

        if (file_put_contents($tmp, $png) === false) {
            throw new RuntimeException("Unable to write card: {$tmp}");
        }

        @chmod($tmp, 0644);

        if (! rename($tmp, $path)) {
            @unlink($tmp);

            throw new RuntimeException("Unable to move card into place: {$path}");
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDirectory($file) : @unlink($file);
        }

        @rmdir($directory);
    }
}
