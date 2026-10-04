<?php

namespace Itqan\PreviewCards\Render;

use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Itqan\PreviewCards\Cache\CardCache;
use Itqan\PreviewCards\Card\CardFactory;
use Itqan\PreviewCards\Settings;
use Psr\Log\LoggerInterface;

class FallbackCards
{
    public function __construct(
        protected CardCache $cache,
        protected CardTemplate $template,
        protected CardRenderer $renderer,
        protected Settings $settings,
        protected CardFactory $factory,
        protected SettingsRepositoryInterface $repository,
        protected UrlGenerator $url,
        protected LoggerInterface $logger,
    ) {
    }

    public function png(?string $locale = null): string
    {
        $locale = ($locale ?? $this->settings->fallbackLocale()) === 'ar' ? 'ar' : 'en';

        $committed = dirname(__DIR__, 2).'/assets/fallback/brand-'.$locale.'.png';

        if (is_file($committed)) {
            $bytes = file_get_contents($committed);

            if ($bytes !== false && str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
                return $bytes;
            }
        }

        $cached = $this->cache->brand($locale);

        if ($cached !== null) {
            return $cached;
        }

        try {
            $png = $this->renderer->render(
                $this->template->forBrand(
                    $locale,
                    $this->factory->brandFor($locale),
                    (string) $this->repository->get('forum_description', ''),
                    $this->factory->colors(),
                    (string) (parse_url($this->url->to('forum')->base(), PHP_URL_HOST) ?: ''),
                    $this->factory->logoDataUri(),
                    $this->factory->backgroundDataUri(),
                ),
                CardTemplate::WIDTH,
                CardTemplate::HEIGHT,
            );

            if (str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
                $this->cache->storeBrand($locale, $png);

                return $png;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[itqan-preview-cards] Brand card render failed: '.$e->getMessage());
        }

        return $this->solid($locale);
    }

    private function solid(string $locale): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==') ?: '';
        }

        $colors = $this->factory->colors();
        [$red, $green, $blue] = sscanf($colors['background'], '#%02x%02x%02x');
        [$ar, $ag, $ab] = sscanf($colors['accent'], '#%02x%02x%02x');

        $image = imagecreatetruecolor(CardTemplate::WIDTH, CardTemplate::HEIGHT);
        $background = imagecolorallocate($image, $red, $green, $blue);
        $accent = imagecolorallocate($image, $ar, $ag, $ab);

        imagefilledrectangle($image, 0, 0, CardTemplate::WIDTH, CardTemplate::HEIGHT, $background);
        imagefilledrectangle($image, 0, 0, CardTemplate::WIDTH, 10, $accent);

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
