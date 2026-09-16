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

use GuzzleHttp\Client;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

class GoogleFree extends AbstractTranslationProvider implements TranslationProviderInterface
{
    /**
     * @var GoogleTranslate
     */
    protected $translator;

    /**
     * @var Client
     */
    protected $httpClient;

    public static string $name = 'google-free';
    public static string $link = ''; // No link provided for GoogleFree

    protected function initialize()
    {
        $this->translator = new GoogleTranslate();
        $this->httpClient = new Client([
            'timeout' => 8,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ],
        ]);
    }

    public function options(): array
    {
        return [];
    }

    protected function translate(string $content, string $toLanguage, string $from = null): string
    {
        $this->ensureInitialized();

        $from = $from ?? 'auto';

        // 1. Try Google Translate via Stichoza configured with googleapis.com & User-Agent
        try {
            $stichoza = new GoogleTranslate($toLanguage, $from, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                ],
                'timeout' => 5,
            ]);
            $stichoza->setUrl('https://translate.googleapis.com/translate_a/single');
            $stichoza->setClient('client6');

            $res = $stichoza->translate($content);
            if (!empty($res)) {
                return $res;
            }
        } catch (Throwable $e) {
            $this->logger->warning("[ianm-translate] GoogleFree Stichoza googleapis endpoint failed: " . $e->getMessage());
        }

        // 2. Try direct Google Translate API query via Guzzle HTTP client
        try {
            $url = 'https://translate.googleapis.com/translate_a/single';
            $response = $this->httpClient->get($url, [
                'query' => [
                    'client' => 'client6',
                    'sl' => $from,
                    'tl' => $toLanguage,
                    'dt' => 't',
                    'q' => $content,
                ],
            ]);

            $bodyArray = json_decode((string) $response->getBody(), true);
            if (is_array($bodyArray) && isset($bodyArray[0]) && is_array($bodyArray[0])) {
                $translated = '';
                foreach ($bodyArray[0] as $segment) {
                    if (isset($segment[0]) && is_string($segment[0])) {
                        $translated .= $segment[0];
                    }
                }
                if (!empty($translated)) {
                    return $translated;
                }
            }
        } catch (Throwable $e) {
            $this->logger->warning("[ianm-translate] GoogleFree direct googleapis GET failed: " . $e->getMessage());
        }

        // 3. Fallback to MyMemory Free Translation API
        try {
            $sourceLang = ($from === 'auto' || empty($from)) ? 'autodetect' : $from;
            $langPair = $sourceLang . '|' . $toLanguage;

            $response = $this->httpClient->get('https://api.mymemory.translated.net/get', [
                'query' => [
                    'q' => $content,
                    'langpair' => $langPair,
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            if (isset($data['responseData']['translatedText']) && !empty($data['responseData']['translatedText'])) {
                return $data['responseData']['translatedText'];
            }
        } catch (Throwable $e) {
            $this->logger->error("[ianm-translate] GoogleFree MyMemory fallback failed: " . $e->getMessage());
        }

        return '';
    }

    protected function identify(string $content): string
    {
        $this->ensureInitialized();

        try {
            $this->translator
                ->setTarget('en')
                ->translate($content);

            $detected = $this->translator->getLastDetectedSource();
            if ($detected) {
                return $detected;
            }
        } catch (Throwable $e) {
            $this->logger->warning("[ianm-translate] GoogleFree identify failed via Stichoza: " . $e->getMessage());
        }

        // Fallback identification via MyMemory
        try {
            $response = $this->httpClient->get('https://api.mymemory.translated.net/get', [
                'query' => [
                    'q' => mb_substr($content, 0, 200),
                    'langpair' => 'autodetect|en',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            if (isset($data['matches'][0]['source']) && is_string($data['matches'][0]['source'])) {
                $src = strtolower(explode('-', $data['matches'][0]['source'])[0]);
                if (!empty($src)) {
                    return $src;
                }
            }
        } catch (Throwable $e) {
            // Silence fallback error
        }

        return 'unknown';
    }

    protected function languages(): array
    {
        // Return supported languages list
        return [
            'af', 'sq', 'am', 'ar', 'hy', 'as', 'az', 'eu', 'bm', 'be', 'bn', 'bs', 'bg', 'ca', 'zh', 'zh-CN', 'zh-TW', 'co', 'hr', 'cs',
            'da', 'dv', 'nl', 'en', 'eo', 'et', 'fi', 'fr', 'fy', 'gl', 'ka', 'de', 'el', 'gn', 'gu', 'ht', 'ha', 'he', 'iw', 'hi', 'hu', 'is',
            'ig', 'id', 'ga', 'it', 'ja', 'jv', 'jw', 'kn', 'kk', 'km', 'rw', 'ko', 'ku', 'ky', 'lo', 'la', 'lv', 'lt', 'lb', 'mk', 'mg', 'ms',
            'ml', 'mt', 'mi', 'mr', 'mn', 'my', 'ne', 'no', 'ny', 'ps', 'fa', 'pl', 'pt', 'pa', 'ro', 'ru', 'sm', 'sa', 'gd', 'sr', 'st', 'sn',
            'sd', 'si', 'sk', 'sl', 'so', 'es', 'su', 'sw', 'sv', 'tl', 'tg', 'ta', 'tt', 'te', 'th', 'tr', 'tk', 'uk', 'ur', 'ug', 'uz', 'vi',
            'cy', 'xh', 'yi', 'yo', 'zu'
        ];
    }
}

