<?php

namespace Itqan\PreviewCards\Console;

use Flarum\Console\AbstractCommand;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Itqan\PreviewCards\Card\CardFactory;
use Itqan\PreviewCards\Render\CardRenderer;
use Itqan\PreviewCards\Render\CardTemplate;
use Symfony\Component\Console\Input\InputOption;

class BrandCommand extends AbstractCommand
{
    public function __construct(
        protected CardTemplate $template,
        protected CardRenderer $renderer,
        protected CardFactory $factory,
        protected SettingsRepositoryInterface $repository,
        protected UrlGenerator $url,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('itqan-cards:brand')
            ->setDescription('Render the committed brand fallback cards into the extension assets')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Only render one language: ar or en');
    }

    protected function fire(): int
    {
        $locales = ['ar', 'en'];

        if ($lang = $this->input->getOption('lang')) {
            $locales = [in_array(strtolower($lang), ['ar', 'en'], true) ? strtolower($lang) : 'en'];
        }

        $directory = dirname(__DIR__, 2).'/assets/fallback';

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            $this->error("Unable to create {$directory}");

            return 1;
        }

        $colors = $this->factory->colors();
        $domain = (string) (parse_url($this->url->to('forum')->base(), PHP_URL_HOST) ?: '');

        foreach ($locales as $locale) {
            $html = $this->template->forBrand(
                $locale,
                $this->factory->brandFor($locale),
                (string) $this->repository->get('forum_description', ''),
                $colors,
                $domain,
                $this->factory->logoDataUri(),
                $this->factory->backgroundDataUri(),
            );

            try {
                $png = $this->renderer->render($html, CardTemplate::WIDTH, CardTemplate::HEIGHT);
            } catch (\Throwable $e) {
                $this->error('Chromium render failed: '.$e->getMessage());

                return 1;
            }

            $path = $directory.'/brand-'.$locale.'.png';

            if (file_put_contents($path, $png) === false) {
                $this->error("Unable to write {$path}");

                return 1;
            }

            $this->info("Wrote {$path} (".strlen($png).' bytes)');
        }

        return 0;
    }
}
