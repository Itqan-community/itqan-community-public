<?php

namespace Irmmr\RTLCss;

use Irmmr\RTLCss\Encoder\ColorFuncsEncoder;
use Irmmr\RTLCss\Encoder\Encoder;

/**
 * class Encode
 *
 * PHP-CSS-Parser have many problems with using css4 or other
 * parsing values.
 * This class is an unreliable class that tries to keep away items
 * that the CSS parser cannot parse or make some changes to the original
 * code so that it can be parsed.
 * ! It is better not to use this class at all.
 *
 * @package Irmmr\RTLCss
 */
class Encode
{
    /**
     * code holder
     * @var string
     */
    protected string $code = '';

    /**
     * encoded code
     * @var string
     */
    protected string $encoded = '';

    /**
     * all encoder classes name
     *
     * @var array
     */
    protected array $encoders = [
        'color-funcs' => ColorFuncsEncoder::class
    ];

    /**
     * all encoder classes (created)
     *
     * @var array
     */
    protected array $classes = [];

    /**
     * class constructor
     */
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * set encoded code
     */
    public function setEncoded(string $encoded): void
    {
        foreach ($this->classes as $id => $cls) {
            if (!$cls instanceof Encoder) {
                continue;
            }

            $cls->setEncoded($encoded);
        }
    }

    /**
     * encode whole code using encode
     *
     * @return string
     */
    public function encode(): string
    {
        if (empty($this->encoded)) {
            $this->encoded = $this->code;
        }

        foreach ($this->encoders as $id => $encoder) {
            $enc = $this->classes[$id] = new $encoder($this->encoded);

            $this->encoded = $enc->encode();
        }

        return $this->encoded;
    }

    /**
     * decode whole code using decode
     *
     * @return string
     */
    public function decode(): string
    {
        $decoded = $this->encoded;

        foreach ($this->classes as $id => $cls) {
            if (!$cls instanceof Encoder) {
                continue;
            }

            $decoded = $cls->decode();
        }

        return $decoded;
    }
}