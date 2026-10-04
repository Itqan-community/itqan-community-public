<?php

namespace Itqan\PreviewCards\Render;

class FontSet
{
    private const FACES = [
        ['NotoSansArabic-arabic.woff2', 'U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC, U+102E0-102FB, U+10E60-10E7E, U+10EC2-10EC4, U+10EFC-10EFF, U+1EE00-1EE03, U+1EE05-1EE1F, U+1EE21-1EE22, U+1EE24, U+1EE27, U+1EE29-1EE32, U+1EE34-1EE37, U+1EE39, U+1EE3B, U+1EE42, U+1EE47, U+1EE49, U+1EE4B, U+1EE4D-1EE4F, U+1EE51-1EE52, U+1EE54, U+1EE57, U+1EE59, U+1EE5B, U+1EE5D, U+1EE5F, U+1EE61-1EE62, U+1EE64, U+1EE67-1EE6A, U+1EE6C-1EE72, U+1EE74-1EE77, U+1EE79-1EE7C, U+1EE7E, U+1EE80-1EE89, U+1EE8B-1EE9B, U+1EEA1-1EEA3, U+1EEA5-1EEA9, U+1EEAB-1EEBB, U+1EEF0-1EEF1'],
        ['NotoSansArabic-latin.woff2', 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD'],
        ['NotoSansArabic-latin-ext.woff2', 'U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF'],
    ];

    private ?array $resolved = null;
    private ?array $sources = null;
    private ?string $fingerprint = null;
    private ?string $css = null;

    /**
     * @param array<string, string> $directories label => directory, in priority order.
     */
    public function __construct(private array $directories)
    {
    }

    public function faceFilenames(): array
    {
        return array_map(fn (array $face) => $face[0], self::FACES);
    }

    /**
     * @return array<string, string> filename => label of the directory that provides it.
     */
    public function sources(): array
    {
        $this->resolve();

        return $this->sources;
    }

    /**
     * @return array<string, string> filename => absolute path.
     */
    public function paths(): array
    {
        $this->resolve();

        return $this->resolved;
    }

    /**
     * @return string[] absolute paths of the resolved faces, in face order.
     */
    public function files(): array
    {
        $this->resolve();

        return array_values($this->resolved);
    }

    public function fingerprint(): string
    {
        if ($this->fingerprint === null) {
            $this->resolve();

            $parts = [];

            foreach (self::FACES as [$file]) {
                $parts[] = isset($this->resolved[$file])
                    ? sha1_file($this->resolved[$file])
                    : $file.':missing';
            }

            $this->fingerprint = substr(sha1(implode('|', $parts)), 0, 12);
        }

        return $this->fingerprint;
    }

    public function inlineCss(): string
    {
        if ($this->css === null) {
            $this->resolve();

            $blocks = [];

            foreach (self::FACES as [$file, $range]) {
                if (! isset($this->resolved[$file])) {
                    continue;
                }

                $encoded = base64_encode((string) file_get_contents($this->resolved[$file]));

                $blocks[] = "@font-face{font-family:'Itqan Cards';font-style:normal;font-weight:400 700;"
                    ."src:url(data:font/woff2;base64,{$encoded}) format('woff2');unicode-range:{$range};}";
            }

            $this->css = implode("\n", $blocks);
        }

        return $this->css;
    }

    private function resolve(): void
    {
        if ($this->resolved !== null) {
            return;
        }

        $this->resolved = [];
        $this->sources = [];

        foreach (self::FACES as [$file]) {
            foreach ($this->directories as $label => $directory) {
                $path = $directory.'/'.$file;

                if (is_file($path)) {
                    $this->resolved[$file] = $path;
                    $this->sources[$file] = $label;

                    break;
                }
            }
        }
    }
}
