<?php

namespace Irmmr\RTLCss\Process;

use Irmmr\RTLCss\Options;
use Irmmr\RTLCss\Traits\OptionsIniTrait;
use Sabberworm\CSS\RuleSet\DeclarationBlock;

/**
 * class Declaration
 * same as RuleSets (keep it for now)
 *
 * process every Declaration block
 *
 * !! KEEP IT FOR NOW
 *
 * @package Irmmr\RTLCss\Process
 */
class Declaration
{
    use OptionsIniTrait;

    /**
     * The node as DeclarationBlock
     * @var  DeclarationBlock
     */
    protected DeclarationBlock $node;

    /**
     * list of all rules
     * @var array
     */
    protected array $rules = [];

    /**
     * list of ignored rules
     * @var array
     */
    protected array $ignores = [];

    /**
     * list of removed rules
     * @var array
     */
    protected array $removes = [];

    /**
     * The function is a PHP constructor.
     *
     * @param DeclarationBlock $node
     * @param Options $options
     */
    public function __construct(DeclarationBlock $node, Options $options)
    {
        $this->node = $node;
        $this->options = $options;

        $this->removes = $this->options->get('removeRules', []);
        $this->ignores = $this->options->get('ignoreRules', []);
    }

    /**
     * _
     * run the main constructor
     *
     * @param DeclarationBlock $node
     * @param Options $options
     */
    public static function parse(DeclarationBlock $node, Options $options): void
    {
        (new Declaration($node, $options))->run();
    }

    /**
     * run this process with
     * applyting all we need to our entry
     */
    public function run(): void
    {
        $option = new Options();

        foreach ($this->node->getRules() as $rule) {
            $property  = $rule->getRule();
            $value     = $rule->getValue();

            if (in_array($property, $this->removes, true)) {
                continue;
            }

            if (!in_array($property, $this->ignores, true)) {
                Rule::parse($rule, $option);
            }

            $this->rules[] = $rule;
        }

        $this->node->setRules( $this->rules );
    }
}