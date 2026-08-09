<?php

namespace Irmmr\RTLCss\Encoder;

/**
 * class RenderColorFuncs
 *
 * The CSS parser cannot correctly parse rgba, rgb, and hsl in CSS4.
 *
 * rgb(from hwb(120deg 10% 20%) r g calc(b + 200))
 * rgb(from #0000FF calc(r + 40) calc(g + 40) b)
 * rgb(255 255 255 / 50%)
 *
 * One of the best solutions is to gather all these functions from within
 * the code and then add them later. I couldn't think of a better idea.
 *
 *
 * @package Irmmr\RTLCss\Encoder
 */
class ColorFuncsEncoder extends Encoder
{
    // :)
    protected const RENDER_EVAL = 'RTL_RENDER_COLOR_VAL_';

    /**
     * color pattern
     * created by @chatgpt
     *
     * @var string
     */
    protected string $pattern = '/\b(?:rgb|rgba|hsl|hsla)\s*\(\s*((?:[^()]+|\((?:[^()]+|\([^()]*\))*\))*)\)/';

    /**
     * detect pattern
     *
     * @var string
     */
    protected string $det_pattern = '/'. self::RENDER_EVAL .'(\d+)/';

    /**
     * all color replacements
     * @var array
     */
    protected array $replacement = [];

    /**
     * get encoded code
     *
     * @return string
     */
    public function encode(): string
    {
        $counter = 1;

        $encoded = preg_replace_callback($this->pattern, function($matches) use (&$counter) {
            $replacement = self::RENDER_EVAL . $counter;

            $this->replacement[$counter] = $matches[0];

            $counter ++;

            return $replacement;
        }, $this->code);

        $this->encoded = $encoded;

        return $encoded;
    }

    /**
     * get decoded code
     *
     * @return string
     */
    public function decode(): string
    {
        return preg_replace_callback($this->det_pattern, function($matches) {
            $index = (int) $matches[1];

            return $this->replacement[$index] ?? $matches[0];
        }, $this->encoded);
    }
}