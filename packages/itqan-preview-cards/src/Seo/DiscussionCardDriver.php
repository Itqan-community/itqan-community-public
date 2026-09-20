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

        if (! $discussion || $this->hasManualImage($id)) {
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

    private function hasManualImage(int $id): bool
    {
        if (! class_exists(SeoMeta::class)) {
            return false;
        }

        $meta = SeoMeta::query()
            ->where('object_type', 'discussions')
            ->where('object_id', $id)
            ->first();

        return $meta !== null && ! empty($meta->open_graph_image);
    }
}
