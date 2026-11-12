<?php

namespace Itqan\PreviewCards\Seo;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Queue\Queue;
use Itqan\PreviewCards\Cache\CardCache;
use Itqan\PreviewCards\Card\CardFactory;
use Itqan\PreviewCards\Card\CardUrlBuilder;
use Itqan\PreviewCards\Job\GenerateCardJob;
use Itqan\PreviewCards\Render\CardTemplate;
use Itqan\PreviewCards\Settings;
use Psr\Http\Message\ServerRequestInterface;
use V17Development\FlarumSeo\Page\PageDriverInterface;
use V17Development\FlarumSeo\SeoMeta\SeoMeta;
use V17Development\FlarumSeo\SeoProperties;

class DiscussionCardDriver implements PageDriverInterface
{
    public function __construct(
        protected Settings $settings,
        protected SettingsRepositoryInterface $repository,
        protected CardFactory $factory,
        protected CardUrlBuilder $urls,
        protected CardCache $cache,
        protected Queue $queue,
    ) {
    }

    public function extensionDependencies(): array
    {
        return ['v17development-seo'];
    }

    public function handleRoutes(): array
    {
        return ['discussion'];
    }

    public function handle(ServerRequestInterface $request, SeoProperties $properties)
    {
        if (! $this->settings->enabled()) {
            return;
        }

        if ((string) $this->repository->get('seo_post_crawler', '0') === '1') {
            return;
        }

        $id = (int) ($request->getQueryParams()['id'] ?? 0);

        if ($id <= 0) {
            return;
        }

        $actor = RequestUtil::getActor($request);
        $discussion = Discussion::whereVisibleTo($actor)->find($id);

        if (! $discussion || $this->hasManualImage($discussion, $properties)) {
            return;
        }

        $locale = $this->factory->localeFor($discussion);
        $data = $this->factory->forDiscussion($discussion, $locale);
        $hash = $this->urls->hash($data);
        $url = $this->urls->absolute($data);
        $alt = $this->factory->altText($data);

        $properties
            ->setImage($url)
            ->setMetaPropertyTag('og:image:width', CardTemplate::WIDTH)
            ->setMetaPropertyTag('og:image:height', CardTemplate::HEIGHT)
            ->setMetaPropertyTag('og:image:type', 'image/png')
            ->setMetaPropertyTag('og:image:alt', $alt)
            ->setMetaTag('twitter:image:alt', $alt);

        if (! $this->cache->has($id, $hash)) {
            $this->queue->push(new GenerateCardJob($id));
        }
    }

    private function hasManualImage(Discussion $discussion, SeoProperties $properties): bool
    {
        if (! class_exists(SeoMeta::class)) {
            return false;
        }

        $meta = SeoMeta::query()
            ->where('object_type', 'discussions')
            ->where('object_id', $discussion->id)
            ->first();

        if ($meta === null) {
            return false;
        }

        $image = $meta->open_graph_image;
        $source = $meta->open_graph_image_source;
        $autoUpdate = (bool) $meta->auto_update_data;

        // Only the ambiguous case needs the post rendered: an image without a
        // recorded source on a row the moderator has frozen.
        $detected = null;

        if (! $autoUpdate && trim((string) $image) !== '' && ! static::hasExplicitSource($source)) {
            try {
                $content = $discussion->firstPost?->formatContent();
                $detected = $content !== null ? $properties->getImageFromContent($content) : null;
            } catch (\Throwable $e) {
                $detected = null;
            }
        }

        return static::isManualImage($image, $source, $autoUpdate, $detected);
    }

    /**
     * Whether the seo_meta image was chosen by a moderator and must keep priority.
     *
     * v17development/flarum-seo copies the first image of the first post into
     * open_graph_image and marks it as source "auto". That image is detected,
     * not chosen, so it does not replace the generated card.
     *
     * The source alone is not enough, though: v17's SEO dialog never sends the
     * source for a typed URL, so a moderator's own image is stored with a NULL
     * source. The dialog only unlocks the field once automatic updates are
     * off, so such a row is manual when it is frozen and its image is not the
     * one v17 would detect in the first post ($detected).
     */
    public static function isManualImage(
        ?string $image,
        ?string $source,
        bool $autoUpdate = true,
        ?string $detected = null,
    ): bool {
        $image = trim((string) $image);

        if ($image === '') {
            return false;
        }

        if (static::hasExplicitSource($source)) {
            return true;
        }

        if ($autoUpdate) {
            return false;
        }

        return $image !== trim((string) $detected);
    }

    private static function hasExplicitSource(?string $source): bool
    {
        $source = strtolower(trim((string) $source));

        return $source !== '' && $source !== 'auto';
    }
}
