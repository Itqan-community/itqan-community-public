<?php

namespace Irmmr\RTLCss\Process;

use Irmmr\RTLCss\Helpers;
use Irmmr\RTLCss\Options;
use Irmmr\RTLCss\Parse\ParseCommaList;
use Irmmr\RTLCss\Process\Swap\SwapBackground;
use Irmmr\RTLCss\Process\Swap\SwapBorderRadius;
use Irmmr\RTLCss\Process\Swap\SwapCursor;
use Irmmr\RTLCss\Process\Swap\SwapShadow;
use Irmmr\RTLCss\Process\Swap\SwapTransform;
use Irmmr\RTLCss\Process\Swap\SwapTransformOrigin;
use Irmmr\RTLCss\Traits\OptionsIniTrait;
use Sabberworm\CSS\Rule\Rule as CssRule;
use Sabberworm\CSS\Value\RuleValueList;

/**
 * class Rule
 *
 * process rule
 *
 * @package Irmmr\RTLCss\Process
 */
class Rule
{
    use OptionsIniTrait;

    /**
     * The node as DeclarationBlock
     * @var  CssRule
     */
    protected CssRule $rule;

    /**
     * The function is a PHP constructor.
     *
     * @param CssRule $rule
     * @param Options $options
     */
    public function __construct(CssRule $rule, Options $options)
    {
        $this->rule     = $rule;
        $this->options  = $options;
    }

    /**
     * _
     * run the main constructor
     *
     * @param CssRule $rule
     * @param Options $options
     */
    public static function parse(CssRule $rule, Options $options): void
    {
        (new Rule($rule, $options))->run();
    }

    /**
     * fix comma parse for backgrounds
     *
     * @param   RuleValueList   $value
     * @return  RuleValueList
     */
    protected function parseCommaValue(RuleValueList $value): RuleValueList
    {
        if (strpos($value->__toString(), ',') !== false) {
            $n_parser = new ParseCommaList( Helpers::getValueStr($value) );
            $n_parser->parse();

            return $n_parser->getList();
        }

        return $value;
    }

    /**
     * run this process with
     * applyting all we need to our entry
     */
    public function run(): void {
        $property  = $this->rule->getRule();
        $value     = $this->rule->getValue();

        // do not apply on variables
        if (substr($property, 0, 2) === '--') {
            return;
        }

        if (preg_match('/direction$/im', $property)) {
            $this->swapLtr($value);

        } else if (preg_match('/left/im', $property)) {
            $this->rule->setRule( str_replace('left', 'right', $property) );

        } else if (preg_match('/right/im', $property)) {
            $this->rule->setRule( str_replace('right', 'left', $property) );

        } else if (preg_match('/transition(-property)?$/i', $property)) {
            $this->swapLeft( Helpers::getValueStr($value) );

        } else if (preg_match('/float|clear|text-align|justify-content|justify-items|justify-self/i', $property)) {
            $this->swapLeft($value);

        } else if (preg_match('/^(margin|padding|border-(color|style|width))$/i', $property)) {
            $this->swapComplex($value);

        } else if (preg_match('/border-radius/i', $property)) {
            $this->swapBorderRadius($value);

        } else if (preg_match('/shadow/i', $property)) {
            $this->swapShadow($value, $property);

        } else if (preg_match('/transform-origin/i', $property)) {
            $this->swapTransformOrigin($value);

        } else if (preg_match('/transform/i', $property)) {
            $this->swapTransform($value);

        } else if (preg_match('/background(-image)?$/i', $property)) {
            $this->swapBackground($value);

        } else if (preg_match('/background-position(-x)?$/i', $property)) {
            $this->swapBackgroundPosition($value, $property);

        } else if (preg_match('/object-position|perspective-origin/i', $property)) {
            $this->swapBackgroundPosition($value, $property);

        } else if (preg_match('/cursor/i', $property)) {
            $this->swapCursor($value);
        }
    }

    /**
     * change the direction from 'ltr' to 'rtl'
     *
     * @param  RuleValueList|string|null  $value
     */
    protected function swapLtr($value): void
    {
        if (!is_string($value)) {
            return;
        }

        $this->rule->setValue( Helpers::swapLtrRtl($value) );
    }

    /**
     * change left and right
     *
     * @param  RuleValueList|string|null  $value
     */
    protected function swapLeft($value): void
    {
        if (!is_string($value)) {
            return;
        }

        $this->rule->setValue( Helpers::swapLeftRight($value) );
    }

    /**
     * swap complex values of css rules
     *
     * @param  RuleValueList|string|null  $value
     */
    protected function swapComplex($value)
    {
        if ($value instanceof RuleValueList) {
            $values = $value->getListComponents();
            $count = count($values);

            if ($count == 4) {
                $right      = $values[3];
                $values[3]  = $values[1];
                $values[1]  = $right;
            }

            $value->setListComponents($values);
        }
    }

    /**
     * swap complex values of css rules
     *
     * @param  RuleValueList|string|null  $value
     */
    protected function swapBorderRadius($value)
    {
        SwapBorderRadius::swap($value, $this->rule, 'border-radius');
    }

    /**
     * swap shadow css
     *
     * @param  RuleValueList|string|null  $value
     * @param  string $property
     */

    protected function swapShadow($value, string $property)
    {
        if ($value instanceof RuleValueList) {
            $value = $this->parseCommaValue($value);
        }

        SwapShadow::swap($value, $this->rule, $property);
    }

    /**
     * swap transform origin
     *
     * @param  RuleValueList|string|null  $value
     */
    protected function swapTransformOrigin($value): void
    {
        SwapTransformOrigin::swap($value, $this->rule, 'transform-origin');
    }

    /**
     * swap transform
     *
     * @param  RuleValueList|string|null  $value
     */
    protected function swapTransform($value): void
    {
        SwapTransform::swap($value, $this->rule, 'transform');
    }

    /**
     * swap background
     *
     * @param  RuleValueList|string|null  $value
     */
    protected function swapBackground($value): void
    {
        if ($value instanceof RuleValueList) {
            $value = $this->parseCommaValue($value);
        }

        SwapBackground::swap($value, $this->rule, 'background');
    }

    /**
     * swap background position
     *
     * @param  RuleValueList|string|null  $value
     * @param  string   $property
     */
    protected function swapBackgroundPosition($value, string $property): void
    {
        if ($value instanceof RuleValueList) {
            $value = $this->parseCommaValue($value);
        }

        SwapBackground::swap($value, $this->rule, $property);
    }

    /**
     * swap cursor
     *
     * @param  RuleValueList|string|null  $value
     */
    protected function swapCursor($value): void
    {
        SwapCursor::swap($value, $this->rule, 'cursor');
    }
}