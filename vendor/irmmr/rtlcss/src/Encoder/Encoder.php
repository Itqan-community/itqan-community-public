<?php

namespace Irmmr\RTLCss\Encoder;

/**
 * abstract class Encoder
 *
 * @package Irmmr\RTLCss\Encoder
 */
abstract class Encoder
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
     * class constructor
     */
    public function __construct(string $code)
    {
        $this->code = $code;
    }

    /**
     * get encoded code
     *
     * @return string
     */
    abstract public function encode(): string;

    /**
     * get decoded code
     *
     * @return string
     */
    abstract public function decode(): string;

    /**
     * get encoded code
     *
     * @return string
     */
    public function getEncoded(): string
    {
        return $this->encoded;
    }

    /**
     * update encoded code
     *
     * @param  string $encoded
     */
    public function setEncoded(string $encoded): void
    {
        $this->encoded = $encoded;
    }

    /**
     * get pure code
     *
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }
}