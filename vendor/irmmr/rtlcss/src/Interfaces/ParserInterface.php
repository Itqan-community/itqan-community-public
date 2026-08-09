<?php

namespace Irmmr\RTLCss\Interfaces;

/**
 * interface ParserInterface
 *
 * @package Irmmr\RTLCss\Interfaces
 */
interface ParserInterface
{
    // parser default options
    public const DEFAULT_OPTIONS = [
        'processComments' => true,
        'ignoreRules'     => []
    ];
}