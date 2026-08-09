<?php

namespace Irmmr\RTLCss\Process\Swap;

use Sabberworm\CSS\Rule\Rule;

/**
 * class Swap
 *
 * @package Irmmr\RTLCss
 */
abstract class Swap
{
    /**
     * name of property
     * @var string
     */
    protected string $property;

    /**
     * value of list
     * @var mixed
     */
    protected $value;

    /**
     * get rule
     * @var Rule
     */
    protected Rule $rule;

    /**
     * class constructor
     *
     * @param mixed $value
     * @param Rule $rule
     * @param string $property
     */
    public function __construct($value, Rule $rule, string $property)
    {
        $this->rule     = $rule;
        $this->value    = $value;
        $this->property = $property;
    }

    /**
     * swap action
     *
     * @param mixed   $value
     * @param Rule    $rule
     * @param string  $property
     *
     * @return void
     */
    public static function swap($value, Rule $rule, string $property): void
    {
        $swap = new static($value, $rule, $property);
        $swap->apply();
    }

    /**
     * apply swap values
     *
     * @return void
     */
    abstract public function apply(): void;
}