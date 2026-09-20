<?php

namespace Itqan\PreviewCards\Cache;

use Flarum\Foundation\Paths;
use Itqan\PreviewCards\Settings;

class RenderLock
{
    public function __construct(
        protected Paths $paths,
        protected Settings $settings,
    ) {
    }

    public function acquire(string $key): ?array
    {
        $directory = $this->paths->storage.'/preview-cards/locks';

        if (! is_dir($directory) && ! @mkdir($directory, 0775, true) && ! is_dir($directory)) {
            return null;
        }

        $hashHandle = fopen($directory.'/'.sha1($key).'.lock', 'c');

        if (! $hashHandle || ! flock($hashHandle, LOCK_EX | LOCK_NB)) {
            if ($hashHandle) {
                fclose($hashHandle);
            }

            return null;
        }

        for ($slot = 0; $slot < $this->settings->renderConcurrency(); $slot++) {
            $slotHandle = fopen($directory.'/slot-'.$slot.'.lock', 'c');

            if ($slotHandle && flock($slotHandle, LOCK_EX | LOCK_NB)) {
                return ['hash' => $hashHandle, 'slot' => $slotHandle];
            }

            if ($slotHandle) {
                fclose($slotHandle);
            }
        }

        flock($hashHandle, LOCK_UN);
        fclose($hashHandle);

        return null;
    }

    public function release(?array $handles): void
    {
        if ($handles === null) {
            return;
        }

        foreach (['slot', 'hash'] as $name) {
            if (! empty($handles[$name]) && is_resource($handles[$name])) {
                flock($handles[$name], LOCK_UN);
                fclose($handles[$name]);
            }
        }
    }
}
