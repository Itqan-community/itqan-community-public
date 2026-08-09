<?php

namespace Irmmr\RTLCss\Process;

use Irmmr\RTLCss\Options;
use Irmmr\RTLCss\Traits\OptionsIniTrait;
use Sabberworm\CSS\CSSList\CSSList as CList;
use Sabberworm\CSS\RuleSet\RuleSet;

/**
 * class CSSList
 *
 * process css list
 *
 * @package Irmmr\RTLCss\Process
 */
class CSSList
{
    use OptionsIniTrait;

    /**
     * The node as CSSList
     * @var  CList
     */
    protected CList $list;

    /**
     * list of every content
     * @var array
     */
    protected array $contents = [];

    /**
     * The function is a PHP constructor.
     *
     * @param CList     $list
     * @param Options   $options
     */
    public function __construct(CList $list, Options $options)
    {
        $this->list = $list;
        $this->options = $options;
    }

    /**
     * _
     * run the main constructor
     *
     * @param CList   $list
     * @param Options $options
     */
    public static function parse(CList $list, Options $options): void
    {
        $css = new CSSList($list, $options);
        $css->run();
    }

    /**
     * run this process with
     * applyting all we need to our entry
     */
    public function run(): void
    {
        foreach ($this->list->getContents() as $node) {
            if ($node instanceof CList) {
                CSSList::parse($node, $this->options);
            }

            if ($node instanceof RuleSet) {
                RuleSets::parse($node, $this->options);
            }

            $this->contents[] = $node;
        }

        $this->list->setContents( $this->contents );
    }
}