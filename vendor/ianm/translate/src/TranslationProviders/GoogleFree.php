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

    private function normalizeLanguageCode(string $code): string
    {
        if (empty($code) || $code === 'auto') {
            return 'auto';
        }
        if (in_array($code, ['zh-CN', 'zh-TW', 'zh-Hans', 'zh-Hant'])) {
            return $code;
        }
        return strtolower(explode('-', $code)[0]);
    }

    protected function translate(string $content, string $toLanguage, string $from = null): string
    {
        $this->ensureInitialized();

        $targetLang = $this->normalizeLanguageCode($toLanguage);
        $sourceLang = ($from !== null && $from !== 'auto') ? $this->normalizeLanguageCode($from) : 'auto';

        // 1. Primary: clients5.google.com dict-chrome-ex (Chrome extension endpoint)
        try {
            $translated = $this->translateViaClients5($content, $targetLang, $sourceLang);
            if (!empty(trim($translated))) {
                return $translated;
            }
        } catch (Throwable $e) {
            $this->logger->warning("[ianm-translate] GoogleFree clients5 translation failed: " . $e->getMessage());
        }

        // 2. Secondary: direct Google Translate API query (translate.googleapis.com)
        try {
            $translated = $this->translateViaGoogle($content, $targetLang, $sourceLang);
            if (!empty(trim($translated))) {
                return $translated;
            }
        } catch (Throwable $e) {
            $this->logger->warning("[ianm-translate] GoogleFree direct googleapis GET failed: " . $e->getMessage());
        }

        // 3. Fallback: MyMemory Free Translation API with intelligent 450-char chunking
        try {
            $translated = $this->translateViaMyMemory($content, $targetLang, $sourceLang);
            if (!empty(trim($translated))) {
                return $translated;
            }
        } catch (Throwable $e) {
            $this->logger->error("[ianm-translate] GoogleFree MyMemory fallback failed: " . $e->getMessage());
        }

        throw new \Exception("All translation engines returned empty result for text length " . mb_strlen($content));
    }

    private function translateViaClients5(string $content, string $toLanguage, string $from): string
    {
        if (mb_strlen($content) <= 1500) {
            return $this->queryClients5Chunk($content, $toLanguage, $from);
        }

        $lines = explode("\n", $content);
        $translatedLines = [];

        foreach ($lines as $line) {
            if (mb_strlen($line) <= 1500) {
                if (trim($line) !== '') {
                    $translatedLines[] = $this->queryClients5Chunk($line, $toLanguage, $from);
                } else {
                    $translatedLines[] = '';
                }
            } else {
                $chunks = $this->splitTextIntoChunks($line, 1500);
                $translatedChunks = [];
                foreach ($chunks as $chunk) {
                    if (trim($chunk) !== '') {
                        $translatedChunks[] = $this->queryClients5Chunk($chunk, $toLanguage, $from);
                    }
                }
                $translatedLines[] = implode(' ', $translatedChunks);
            }
        }

        return implode("\n", $translatedLines);
    }

    private function queryClients5Chunk(string $chunk, string $toLanguage, string $from): string
    {
        $url = 'https://clients5.google.com/translate_a/t';
        $response = $this->httpClient->get($url, [
            'query' => [
                'client' => 'dict-chrome-ex',
                'sl' => $from,
                'tl' => $toLanguage,
                'q' => $chunk,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        if (is_array($data) && isset($data[0])) {
            $item = $data[0];
            if (is_array($item)) {
                return (string) ($item[0] ?? '');
            } elseif (is_string($item)) {
                return $item;
            }
        }

        return '';
    }

    private function translateViaGoogle(string $content, string $toLanguage, string $from): string
    {
        if (mb_strlen($content) <= 1500) {
            return $this->queryGoogleChunk($content, $toLanguage, $from);
        }

        $lines = explode("\n", $content);
        $translatedLines = [];

        foreach ($lines as $line) {
            if (mb_strlen($line) <= 1500) {
                if (trim($line) !== '') {
                    $translatedLines[] = $this->queryGoogleChunk($line, $toLanguage, $from);
                } else {
                    $translatedLines[] = '';
                }
            } else {
                $chunks = $this->splitTextIntoChunks($line, 1500);
                $translatedChunks = [];
                foreach ($chunks as $chunk) {
                    if (trim($chunk) !== '') {
                        $translatedChunks[] = $this->queryGoogleChunk($chunk, $toLanguage, $from);
                    }
                }
                $translatedLines[] = implode(' ', $translatedChunks);
            }
        }

        return implode("\n", $translatedLines);
    }

    private function queryGoogleChunk(string $chunk, string $toLanguage, string $from): string
    {
        $url = 'https://translate.googleapis.com/translate_a/single';
        $response = $this->httpClient->get($url, [
            'query' => [
                'client' => 'client6',
                'sl' => $from,
                'tl' => $toLanguage,
                'dt' => 't',
                'q' => $chunk,
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

        return '';
    }

    private function translateViaMyMemory(string $content, string $toLanguage, string $from): string
    {
        $sourceLang = ($from === 'auto' || empty($from)) ? 'autodetect' : $from;
        $langPair = $sourceLang . '|' . $toLanguage;

        $lines = explode("\n", $content);
        $translatedLines = [];

        foreach ($lines as $line) {
            if (mb_strlen($line) <= 450) {
                if (trim($line) !== '') {
                    $translatedLines[] = $this->queryMyMemoryChunk($line, $langPair);
                } else {
                    $translatedLines[] = '';
                }
            } else {
                $chunks = $this->splitTextIntoChunks($line, 450);
                $translatedChunks = [];
                foreach ($chunks as $chunk) {
                    if (trim($chunk) !== '') {
                        $translatedChunks[] = $this->queryMyMemoryChunk($chunk, $langPair);
                    }
                }
                $translatedLines[] = implode(' ', $translatedChunks);
            }
        }

        return implode("\n", $translatedLines);
    }

    private function queryMyMemoryChunk(string $chunk, string $langPair): string
    {
        $response = $this->httpClient->get('https://api.mymemory.translated.net/get', [
            'query' => [
                'q' => $chunk,
                'langpair' => $langPair,
            ],
        ]);

        $data = json_decode((string) $response->getBody(), true);
        if (isset($data['responseData']['translatedText']) && !empty($data['responseData']['translatedText'])) {
            $text = $data['responseData']['translatedText'];
            if (str_contains($text, 'QUERY LENGTH LIMIT EXCEEDED')) {
                return $chunk;
            }
            return $text;
        }

        return $chunk;
    }

    private function splitTextIntoChunks(string $text, int $maxLength): array
    {
        $words = explode(' ', $text);
        $chunks = [];
        $currentChunk = '';

        foreach ($words as $word) {
            if (mb_strlen($currentChunk . ' ' . $word) <= $maxLength) {
                $currentChunk = $currentChunk === '' ? $word : $currentChunk . ' ' . $word;
            } else {
                if ($currentChunk !== '') {
                    $chunks[] = $currentChunk;
                }
                if (mb_strlen($word) > $maxLength) {
                    $chunks[] = mb_substr($word, 0, $maxLength);
                    $currentChunk = mb_substr($word, $maxLength);
                } else {
                    $currentChunk = $word;
                }
            }
        }

        if ($currentChunk !== '') {
            $chunks[] = $currentChunk;
        }

        return $chunks;
    }

    protected function identify(string $content): string
    {
        $this->ensureInitialized();

        // 1. Direct query to clients5.google.com with dict-chrome-ex
        try {
            $url = 'https://clients5.google.com/translate_a/t';
            $response = $this->httpClient->get($url, [
                'query' => [
                    'client' => 'dict-chrome-ex',
                    'sl' => 'auto',
                    'tl' => 'en',
                    'q' => mb_substr($content, 0, 300),
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);
            if (is_array($data) && isset($data[0][1]) && is_string($data[0][1])) {
                $lang = strtolower(explode('-', $data[0][1])[0]);
                if (!empty($lang) && $lang !== 'unknown' && $lang !== 'auto') {
                    return $lang;
                }
            }
        } catch (Throwable $e) {
            $this->logger->warning("[ianm-translate] GoogleFree clients5 identify failed: " . $e->getMessage());
        }

        // 2. Direct query to translate.googleapis.com with client6
        try {
            $url = 'https://translate.googleapis.com/translate_a/single';
            $response = $this->httpClient->get($url, [
                'query' => [
                    'client' => 'client6',
                    'sl' => 'auto',
                    'tl' => 'en',
                    'dt' => 't',
                    'q' => mb_substr($content, 0, 300),
                ],
            ]);

            $bodyArray = json_decode((string) $response->getBody(), true);
            if (is_array($bodyArray) && isset($bodyArray[2]) && is_string($bodyArray[2])) {
                $lang = strtolower(explode('-', $bodyArray[2])[0]);
                if (!empty($lang) && $lang !== 'unknown' && $lang !== 'auto') {
                    return $lang;
                }
            }
        } catch (Throwable $e) {
            $this->logger->warning("[ianm-translate] GoogleFree direct identify failed: " . $e->getMessage());
        }

        // 3. Fallback identification via MyMemory
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
                if (!empty($src) && $src !== 'autodetect') {
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


