<?php

namespace IanM\Translate\Providers;

use Flarum\Foundation\AbstractServiceProvider;
use IanM\Translate\TranslationProviders;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;
use Illuminate\Contracts\Container\Container as Container;

class PostTranslatorProvider extends AbstractServiceProvider
{
    public function register()
    {
        $providers = [
            TranslationProviders\GoogleFree::$name => TranslationProviders\GoogleFree::class,
            TranslationProviders\GoogleCloud::$name => TranslationProviders\GoogleCloud::class,
            TranslationProviders\DeepL::$name => TranslationProviders\DeepL::class,
            // Add more providers as needed using their static name as the key
        ];
        
        $this->container->tag($providers, 'ianm-translate.providers');

        $this->container->singleton(TranslationProviderInterface::class, function (Container $container) use ($providers) {
            return $this->resolveCurrentProvider($container, $providers);
        });
                
        $this->container->singleton('ianm-translate.providers.admin', function (Container $container) use ($providers) {
            return $this->map([$this, 'prepareProviderPayload'], $providers)($container);
        });        
    }

    public function resolveCurrentProvider(Container $container, array $providers)
    {
        $settings = $container->make('flarum.settings');
        $currentProviderName = $settings->get('ianm-translate.provider');

        if (isset($providers[$currentProviderName])) {
            return $container->make($providers[$currentProviderName]);
        }

        return $this->fallbackProvider($container, $providers);
    }

    private function fallbackProvider(Container $container, array $providers)
    {
        return $container->make($providers[TranslationProviders\GoogleFree::$name]);
    }

    protected function prepareProviderPayload(TranslationProviderInterface $provider)
    {
        return [
            'name' => $provider->name(),
            'enabled' => $provider->enabled(),
            'options' => $provider->options(),
            'link' => $provider->link(),
        ];
    }

    protected function map(callable $cb, array $providers)
    {
        return function () use ($cb, $providers) {
            $providers = array_map([$this->container, 'make'], $providers);

            return array_map(function ($provider) use ($cb) {
                return $cb($provider);
            }, $providers);
        };
    }
}
