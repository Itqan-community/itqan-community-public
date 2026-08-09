<?php

namespace Irmmr\RTLCss;

/**
 * class Options
 *
 * @package Irmmr\RTLCss
 * options handler
 */
class Options
{
    /**
     * default options
     * @var array
     */
    protected array $def_options = [];

    /**
     * all options holder
     * @var array
     */
    protected array $options = [];

    /**
     * class constructor
     *
     * @param array $def_options
     */
    public function __construct(array $def_options = [])
    {
        $this->options = $this->def_options = $def_options;
    }

    /**
     * set options (merge)
     *
     * @array $options
     */
    public function set(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * reset options
     *
     * @array $options
     */
    public function reset(): void
    {
        $this->options = $this->def_options;
    }

    /**
     * get options
     *
     * @param  string|null $key
     * @param  null        $def
     * @return mixed
     */
    public function get(?string $key = null, $def = null)
    {
        if (!is_null($key)) {
            return $this->has($key) ? $this->options[$key] : $def;
        }

        return $this->options;
    }

    /**
     * check options
     *
     * @param string|null $key
     * @return bool
     */
    public function has(?string $key = null): bool
    {
        if (is_null($key)) {
            return !empty($this->options);
        }

        return array_key_exists($key, $this->options);
    }

    /**
     * create options if not exists
     *
     * @param array $options
     * @param mixed $value
     */
    public function create(array $options, $value): void
    {
        foreach ($options as $option) {
            if (!$this->has($option)) {
                $this->set([ $option => $value ]);
            }
        }
    }
}