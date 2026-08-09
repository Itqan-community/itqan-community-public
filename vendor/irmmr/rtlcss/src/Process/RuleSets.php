<?php

namespace Irmmr\RTLCss\Process;

use Irmmr\RTLCss\Options;
use Irmmr\RTLCss\Traits\OptionsIniTrait;
use Sabberworm\CSS\RuleSet\RuleSet;

/**
 * class RuleSets
 *
 * process every RuleSet block
 *
 * @package Irmmr\RTLCss\Process
 */
class RuleSets
{
    use OptionsIniTrait;

    /**
     * The node as DeclarationBlock
     * @var  RuleSet
     */
    protected RuleSet $sets;

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
     * @param RuleSet $sets
     * @param Options $options
     */
    public function __construct(RuleSet $sets, Options $options)
    {
        $this->sets     = $sets;
        $this->options  = $options;

        $this->removes = $this->options->get('removeRules', []);
        $this->ignores = $this->options->get('ignoreRules', []);
    }

    /**
     * _
     * run the main constructor
     *
     * @param RuleSet $sets
     * @param Options $options
     */
    public static function parse(RuleSet $sets, Options $options): void
    {
        (new RuleSets($sets, $options))->run();
    }

    /**
     * run this process with
     * applyting all we need to our entry
     */
    public function run(): void
    {
        $option = new Options();

        foreach ($this->sets->getRules() as $rule) {
            $property  = $rule->getRule();
            //$value     = $rule->getValue();

            if (in_array($property, $this->removes, true)) {
                continue;
            }

            if (!in_array($property, $this->ignores, true)) {
                Rule::parse($rule, $option);
            }

            $this->rules[] = $rule;
        }

        $this->sets->setRules( $this->rules );
    }
}