<?php

namespace Irmmr\RTLCss\Process;

use Irmmr\RTLCss\Directives\Directive;
use Irmmr\RTLCss\Options;
use Irmmr\RTLCss\Traits\OptionsIniTrait;
use Sabberworm\CSS\CSSList\CSSList;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\Parsing\UnexpectedTokenException;
use Sabberworm\CSS\RuleSet\DeclarationBlock;
use Sabberworm\CSS\RuleSet\RuleSet;
use Irmmr\RTLCss\Process\CSSList as ProCSSList;
use Sabberworm\CSS\Rule\Rule as CSSRule;

/**
 * class Block
 *
 * main
 * process whole block
 *
 * @package Irmmr\RTLCss\Process
 */
class Block
{
    use OptionsIniTrait;

    /**
     * The block as DeclarationBlock
     * @var  Document
     */
    protected Document $block;

    /**
     * list of every content
     * @var array
     */
    protected array $contents = [];

    /**
     * The function is a PHP constructor.
     *
     * @param Document $block
     * @param Options  $options
     */
    public function __construct(Document $block, Options $options)
    {
        $this->block    = $block;
        $this->options  = $options;
    }

    /**
     * _
     * run the main constructor
     *
     * @param Document $block
     * @param Options  $options
     */
    public static function parse(Document $block, Options $options): void
    {
        $block = new Block($block, $options);
        $block->run();
    }

    /**
     * parse every node belongs to Block
     *
     * @param mixed   $node
     * @param Options $options
     */
    private function parseNode($node, Options $options): void
    {
        if ($node instanceof CSSList) {
            ProCSSList::parse($node, $options);
        } else if ($node instanceof RuleSet) {
            RuleSets::parse($node, $options);
        }
    }

    /**
     * run this process with
     * applyting all we need to our entry
     */
    public function run(): void
    {
        $process_comments = $this->options()->get('processComments', true);

        foreach ($this->block->getContents() as $node) {
            // do not check any comment and commands
            if (!$process_comments) {
                $this->parseNode($node, new Options);

                // apply node
                $this->contents[] = $node;

                continue;
            }

            $comments  = $node->getComments();

            // parse comments for commands
            $directive = new Directive($comments);
            $directive->parse();

            // get all results of actions
            $commands = $directive->getResults();

            // should remove
            if ($commands['total-remove']) {
                continue;
            }

            // should rename
            if (!empty($commands['rename']) && $node instanceof  DeclarationBlock) {
                try {
                    $node->setSelectors($commands['rename']);
                } catch (UnexpectedTokenException $e) {
                    // nothing to do
                }
            }

            // should discard selectors
            if (!empty($commands['discard']) && $node instanceof DeclarationBlock) {
                foreach ($commands['discard'] as $selector) {
                    $node->removeSelector($selector);
                }

                // check if node has selector after discarding
                if (empty( $node->getSelectors() )) {
                    continue;
                }
            }

            // should ignore
            if (!$commands['total-ignore']) {
                $opt = new Options([
                    'ignoreRules' => \array_merge((array) $this->options->get('ignoreRules', []), $commands['ignore']),
                    'removeRules' => $commands['remove']
                ]);

                $this->parseNode($node, $opt);
            }

            // should add raw css
            if (!empty($commands['raw']) && $node instanceof DeclarationBlock) {
                foreach ($commands['raw'] as $rule) {
                    if ($rule instanceof CSSRule) {
                        $node->addRule($rule);
                    }
                }
            }

            $this->contents[] = $node;
        }

        $this->block->setContents( $this->contents );
    }
}