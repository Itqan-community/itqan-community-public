<?php

namespace IanM\Translate\TranslationProviders;

use DeepL\TranslateTextOptions;
use DeepL\Translator;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class DeepL extends AbstractTranslationProvider implements TranslationProviderInterface
{
    public static string $name = 'deepl';
    public static string $link = 'https://www.deepl.com/pro#developer';

    protected function initialize()
    {
        if (empty($key = $this->getSetting('api_key'))) {
            return;
        }
        $this->translator = new Translator($key);
    }

    public function options(): array
    {
        return [
            'api_key' => ['type' => 'string', 'required' => true],
            'formality' => ['type' => 'select', 'required' => false, 'options' => ['default', 'prefer_more', 'prefer_less']],
            'en_choice' => ['type' => 'select', 'required' => false, 'options' => ['en-US', 'en-GB']],
        ];
    }

    protected function translate(string $content, string $toLanguage, string $from = null): string
    {
        $this->ensureInitialized();
        
        if ($toLanguage === 'en') {
            $toLanguage = $this->getSetting('en_choice');
        }

        $result = $this->translator->translateText(
            $content,
            $from,
            $toLanguage,
            [TranslateTextOptions::FORMALITY => $this->getSetting('formality')]
        );

        return $result->text;
    }

    public function identify(string $content): string
    {
        $this->ensureInitialized();
        
        // $result = $this->translator->translateText($content, null, $this->getSetting('en_choice'));

        // return $result->detectedSourceLang;

        // DeepL doesn't support language detection, so we'll use Google Translate instead.
        /** @var GoogleFree $google */
        $google = resolve(GoogleFree::class);

        return $google->identify($content);
    }

    public function languages(): array
    {
        $this->ensureInitialized();
        
        if (!$langs = $this->getSetting('supported_languages')) {
            $l = [];

            foreach ($this->translator->getTargetLanguages() as $lang) {
                if (Str::length($lang->code) > 2) {
                    $lang->code = Str::substr($lang->code, 0, 2);
                }

                if (Arr::get($l, $lang->code)) {
                    continue;
                }

                $lower = Str::lower($lang->code);

                $l[$lower] = $lower;
            }
            $langs = json_encode(array_keys($l));
            $this->putSetting('supported_languages', $langs);
        }
        return json_decode($langs);
    }
}
