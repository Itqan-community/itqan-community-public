<?php

namespace Itqan\PreviewCards\Api;

use Flarum\Api\Schema;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

/**
 * Exposes the custom card logo/background URLs to the forum payload.
 *
 * Flarum 2.0 replaced the `ForumSerializer` attribute extenders with
 * blueprint resource fields, so the admin UI reads these from `ForumResource`
 * instead of a flat serializer attribute list.
 */
class ForumResourceFields
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected FilesystemFactory $filesystem,
    ) {
    }

    public function __invoke(): array
    {
        return [
            Schema\Str::make('itqanPreviewCardsLogoUrl')
                ->nullable()
                ->get(fn () => $this->assetUrl('itqan-preview-cards.logo_path')),

            Schema\Str::make('itqanPreviewCardsBackgroundUrl')
                ->nullable()
                ->get(fn () => $this->assetUrl('itqan-preview-cards.background_path')),
        ];
    }

    private function assetUrl(string $key): ?string
    {
        $path = (string) $this->settings->get($key, '');

        if ($path === '') {
            return null;
        }

        return $this->filesystem->disk('flarum-assets')->url($path);
    }
}
