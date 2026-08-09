<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate\TranslationProviders;

use Jefs42\LibreTranslate;

class Libre extends AbstractTranslationProvider implements TranslationProviderInterface
{
    public static string $name = 'libre';
    public static string $link = 'https://github.com/LibreTranslate/LibreTranslate/blob/main/README.md';

    protected function initialize()
    {
        $provider = new LibreTranslate(
            $this->getSetting('url'),
            (int) $this->getSetting('port')
        );

        if (!empty($apiKey = $this->getSetting('api_key'))) {
            $provider->setApiKey($apiKey);
        }

        $this->translator = $provider;
    }

    public function options(): array
    {
        return [
            'url' => ['type' => 'url'],
            'port' => ['type' => 'number'],
            'api_key' => ['type' => 'string'],
        ];
    }

    protected function translate(string $content, string $toLanguage, string $from = null): string
    {
        $this->ensureInitialized();
        
        if (empty($from)) {
            $from = 'auto';
        }

        return $this->translator->translate($content, $from, $toLanguage);
    }

    protected function identify(string $content): string
    {
        $this->ensureInitialized();
        
        return $this->translator->detect($content);
    }

    protected function languages(): array
    {
        $this->ensureInitialized();
        
        if (!$langs = $this->getSetting('supported_languages') || $this->getSetting('supported_languages.server') !== $server = $this->getSetting('url')) {
            $langs = json_encode(array_keys($this->translator->Languages()));
            $this->putSetting('supported_languages', $langs);
            $this->putSetting('supported_languages.server', $server);
        }
        return json_decode($langs);
    }
}
