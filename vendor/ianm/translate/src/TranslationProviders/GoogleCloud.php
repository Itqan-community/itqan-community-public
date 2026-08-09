<?php

namespace IanM\Translate\TranslationProviders;

use Google\Cloud\Translate\V2\TranslateClient;
use IanM\Translate\Exception\TranslationException;
use Illuminate\Support\Arr;
use Throwable;

class GoogleCloud extends AbstractTranslationProvider implements TranslationProviderInterface
{
    public static string $name = 'google-cloud';
    public static string $link = 'https://cloud.google.com/translate/docs/setup';

    protected function initialize()
    {
        $this->translator = new TranslateClient([
            'key' => $this->getSetting('api_key')
        ]);
    }

    public function options(): array
    {
        return [
            'api_key' => ['type' => 'string', 'required' => true],
        ];
    }

    protected function translate(string $content, string $toLanguage, string $from = null): string
    {
        $this->ensureInitialized();
        
        $result = $this->translator->translate($content, [
            'target' => $toLanguage,
            'format' => 'text'
        ]);

        if (!$result) {
            throw new TranslationException('Translation failed');
        }

        return Arr::get($result, 'text');
    }

    protected function identify(string $content): string
    {
        $this->ensureInitialized();
        
        $result = $this->translator->detectLanguage($content);

        if (!$result) {
            throw new TranslationException('Language detection failed');
        }

        return Arr::get($result, 'languageCode');
    }

    protected function languages(): array
    {
        $this->ensureInitialized();
        
        if (!$langs = $this->getSetting('supported_languages')) {
            $langs = json_encode($this->translator->languages());
            $this->putSetting('supported_languages', $langs);
        }
        return json_decode($langs);
    }
}
