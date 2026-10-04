<?php

namespace Itqan\PreviewCards\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Discussion\Discussion;
use Itqan\PreviewCards\Cache\CardCache;
use Itqan\PreviewCards\Card\CardFactory;
use Itqan\PreviewCards\Card\CardUrlBuilder;
use Itqan\PreviewCards\Render\CardRenderer;
use Itqan\PreviewCards\Render\CardTemplate;
use Itqan\PreviewCards\Settings;
use Symfony\Component\Console\Input\InputOption;

class WarmCommand extends AbstractCommand
{
    public function __construct(
        protected Settings $settings,
        protected CardFactory $factory,
        protected CardUrlBuilder $urls,
        protected CardCache $cache,
        protected CardTemplate $template,
        protected CardRenderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('itqan-cards:warm')
            ->setDescription('Pre-generate preview cards for recently active discussions')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'How many discussions to walk', '50')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-render cards that are already cached');
    }

    protected function fire(): int
    {
        if (! $this->settings->enabled()) {
            $this->error('Preview cards are disabled.');

            return 1;
        }

        $limit = max(1, (int) $this->input->getOption('limit'));
        $force = (bool) $this->input->getOption('force');
        $rendered = 0;
        $skipped = 0;
        $failed = 0;

        $discussions = Discussion::query()
            ->orderByDesc('last_posted_at')
            ->limit($limit)
            ->get();

        foreach ($discussions as $discussion) {
            $locale = $this->factory->localeFor($discussion);
            $data = $this->factory->forDiscussion($discussion, $locale);
            $hash = $this->urls->hash($data);

            if (! $force && $this->cache->has((int) $discussion->id, $hash)) {
                $skipped++;
                continue;
            }

            try {
                $png = $this->renderer->render(
                    $this->template->forDiscussion($data),
                    CardTemplate::WIDTH,
                    CardTemplate::HEIGHT,
                );

                $this->cache->store((int) $discussion->id, $hash, $png);
                $rendered++;
                $this->info("Rendered #{$discussion->id} [{$locale}] {$discussion->title}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("Failed #{$discussion->id}: {$e->getMessage()}");
            }
        }

        $this->info("Done. rendered={$rendered} skipped={$skipped} failed={$failed}");

        return $failed > 0 && $rendered === 0 ? 1 : 0;
    }
}
