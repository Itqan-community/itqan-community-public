<?php

namespace Irmmr\RTLCss\Directives;

/**
 * class Command
 *
 * @package Irmmr\RTLCss\Directives
 */
class Command
{
    /**
     * command name
     * @var string
     */
    protected string $name;

    /**
     * command value
     * @var string|null
     */
    protected ?string $value;

    /**
     * The function is a PHP constructor.
     *
     * @param   string          $name
     * @param   string|null     $value
     */
    public function __construct(string $name, ?string $value = '')
    {
        $this->name     = $name;
        $this->value    = empty($value) ? null : $value;
    }

    /**
     * get command name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * get command value
     *
     * @return string|null
     */
    public function getValue(): ?string
    {
        return $this->value;
    }

    /**
     * command include any value?
     *
     * @return bool
     */
    public function hasValue(): bool
    {
        return !is_null($this->value);
    }

    /**
     * check command name
     *
     * @param   string $name
     * @return  bool
     */
    public function is(string $name): bool
    {
        return $this->name === $name;
    }
}