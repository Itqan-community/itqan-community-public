<?php

namespace Itqan\PreviewCards;

use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\AbstractServiceProvider;
use Itqan\PreviewCards\Card\CardFactory;
use Itqan\PreviewCards\Render\CardRenderer;
use Itqan\PreviewCards\Render\ChromiumRenderer;
use Itqan\PreviewCards\Render\FontSet;

class PreviewCardsServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->singleton(FontSet::class, function ($container) {
            $directories = [];

            /** @var ExtensionManager $manager */
            $manager = $container->make(ExtensionManager::class);

            if ($manager->isEnabled('itqan-typography')) {
                $path = $manager->getExtension('itqan-typography')?->getPath();

                if ($path !== null && is_dir($path.'/assets/fonts')) {
                    $directories['itqan-typography'] = $path.'/assets/fonts';
                }
            }

            $directories['bundled'] = dirname(__DIR__).'/assets/fonts';

            return new FontSet($directories);
        });

        $this->container->singleton(CardFactory::class);

        $this->container->singleton(CardRenderer::class, ChromiumRenderer::class);
    }
}
