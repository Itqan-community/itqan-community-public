<?php

namespace Itqan\Translate;

use RuntimeException;

/**
 * Free Google translate endpoint.
 *
 * The 1.x provider (and the library it used) called `translate.google.com`,
 * which is blocked on our servers; `translate.googleapis.com` answers the same
 * `translate_a/single` format and is reachable. Requests are bounded so a stuck
 * provider cannot hang a queue worker or a request.
 */
class TranslationProvider
{
    private const MAX_CHUNK = 4000;

    public function translate(string $text, string $language): string
    {
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= self::MAX_CHUNK) {
            return $this->request($text, $language);
        }

        $chunks = preg_split('/\n{2,}/u', $text) ?: [$text];

        $out = [];
        $buffer = '';

        foreach ($chunks as $chunk) {
            if ($buffer !== '' && mb_strlen($buffer) + mb_strlen($chunk) > self::MAX_CHUNK) {
                $out[] = $this->request($buffer, $language);
                $buffer = $chunk;
            } else {
                $buffer = $buffer === '' ? $chunk : $buffer."\n\n".$chunk;
            }
        }

        if ($buffer !== '') {
            $out[] = $this->request($buffer, $language);
        }

        return implode("\n\n", $out);
    }

    private function request(string $text, string $language): string
    {
        $url = 'https://translate.googleapis.com/translate_a/single'
            .'?client=gtx&sl=auto&dt=t&tl='.rawurlencode($language)
            .'&q='.rawurlencode($text);

        [$body, $status, $error] = $this->send($url);

        // The free endpoint throttles bursts; one short retry rides out a 429
        // without hiding a real outage.
        if ($status === 429) {
            sleep(2);
            [$body, $status, $error] = $this->send($url);
        }

        if ($body === false || $status >= 400) {
            throw new RuntimeException("provider failed (status={$status}, error={$error})");
        }

        $json = json_decode($body, true);
        $translated = '';

        foreach (($json[0] ?? []) as $part) {
            if (is_array($part) && isset($part[0])) {
                $translated .= $part[0];
            }
        }

        if (trim($translated) === '') {
            throw new RuntimeException('provider returned an empty translation');
        }

        return $translated;
    }

    /**
     * @return array{0: string|false, 1: int, 2: string}
     */
    private function send(string $url): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ItqanTranslate/1.0)',
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return [$body, $status, $error];
    }
}
