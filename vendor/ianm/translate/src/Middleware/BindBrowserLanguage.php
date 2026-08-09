<?php

namespace IanM\Translate\Middleware;

use Flarum\Locale\Translator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BindBrowserLanguage implements MiddlewareInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Translator $translator,
        protected Container $container
        ){}
    
        public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
        {
            if ($this->shouldBindLanguage()) {
                $browserLanguages = $this->determineLanguageFromBrowserRequest($this->getBrowserLanguages($request));
                $this->container->instance('ianm-translate.request.langs', $browserLanguages);
            }
    
            return $handler->handle($request);
        }
    
        protected function shouldBindLanguage(): bool
        {
            return (bool) $this->settings->get('ianm-translate.bind-browser-language');
        }
    
        protected function getBrowserLanguages(ServerRequestInterface $request): string
        {
            return Arr::get($request->getServerParams(), 'HTTP_ACCEPT_LANGUAGE', $this->translator->getLocale());
        }
    
        protected function determineLanguageFromBrowserRequest(string $acceptLangs): array
        {
            $langs = $this->parseAcceptLanguage($acceptLangs);
            arsort($langs, SORT_NUMERIC);
    
            return array_keys($langs);
        }
    
        protected function parseAcceptLanguage(string $acceptLangs): array
        {
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptLangs, $lang_parse);
    
            if (count($lang_parse[1]) === 0) {
                return [];
            }
    
            $langs = array_combine($lang_parse[1], array_map(function ($q) {
                return $q === '' ? 1 : (float) $q;
            }, $lang_parse[4]));
    
            return $langs;
        }
}
