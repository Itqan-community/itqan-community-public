<?php

namespace Irmmr\RTLCss;

use Irmmr\RTLCss\Interfaces\ParserInterface;
use Irmmr\RTLCss\Process\Block;
use Irmmr\RTLCss\Traits\OptionsIniTrait;
use Sabberworm\CSS\CSSList\Document;

/**
 * @see https://github.com/moodlehq/rtlcss-php
 *
 * This project is a revised version of the above project.
 * I had to rewrite parts of this project to fix some issues and add some features.
 */

/**
 * class Parser
 *
 * @package Irmmr\RTLCss
 * main class for accessing RTLCss!
 */
class Parser implements ParserInterface
{
    use OptionsIniTrait;

    /**
     * Parsed tree of css
     * @var  Document
     */
    protected Document $tree;

    /**
     * The function is a PHP constructor that initializes a property with a Document object.
     *
     * @param Document  $tree    tree The `tree` parameter in the constructor is of type `Document`.
     * @param array     $options
     */
    public function __construct(Document $tree, array $options = [])
    {
        $this->tree = $tree;

        $this->options = new Options(self::DEFAULT_OPTIONS);

        !empty($options) && $this->options->set($options);
    }

    /**
     * The flip function processes a block within a document and returns the modified document.
     *
     * @return Document
     */
    public function flip(): Document
    {
        Block::parse($this->tree, $this->options);

        return $this->tree;
    }
}