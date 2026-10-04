<?php

namespace Itqan\PreviewCards\Job;

use Flarum\Discussion\Discussion;
use Flarum\Queue\AbstractJob;
use Itqan\PreviewCards\Cache\CardCache;
use Itqan\PreviewCards\Card\CardFactory;
use Itqan\PreviewCards\Card\CardUrlBuilder;
use Itqan\PreviewCards\Render\CardRenderer;
use Itqan\PreviewCards\Render\CardTemplate;
use Itqan\PreviewCards\Settings;
use Psr\Log\LoggerInterface;

class GenerateCardJob extends AbstractJob
{
    public function __construct(protected int $discussionId)
    {
        parent::__construct();
    }

    public function handle(
        Settings $settings,
        CardFactory $factory,
        CardUrlBuilder $urls,
        CardCache $cache,
        CardTemplate $template,
        CardRenderer $renderer,
        LoggerInterface $logger,
    ): void {
        if (! $settings->enabled()) {
            return;
        }

        $discussion = Discussion::find($this->discussionId);

        if (! $discussion) {
            $cache->deleteDiscussion($this->discussionId);

            return;
        }

        $locale = $factory->localeFor($discussion);
        $data = $factory->forDiscussion($discussion, $locale);
        $hash = $urls->hash($data);

        if ($cache->has($this->discussionId, $hash)) {
            return;
        }

        try {
            $png = $renderer->render(
                $template->forDiscussion($data),
                CardTemplate::WIDTH,
                CardTemplate::HEIGHT,
            );

            if (! str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
                throw new \RuntimeException('Renderer returned a non-PNG payload.');
            }

            $cache->store($this->discussionId, $hash, $png);
        } catch (\Throwable $e) {
            $logger->warning('[itqan-preview-cards] Queued card render failed for discussion '.$this->discussionId.': '.$e->getMessage());
        }
    }
}
