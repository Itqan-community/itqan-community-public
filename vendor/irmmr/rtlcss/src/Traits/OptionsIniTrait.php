<?php

namespace Irmmr\RTLCss\Traits;

use Irmmr\RTLCss\Options;

/**
 * trait OptionsIniTrait
 *
 * @package Irmmr\RTLCss\Traits
 */
 trait OptionsIniTrait
 {
     /**
     * all options holder
     * @var Options
     */
     protected Options $options;

    /**
     * get options class
     *
     * @return Options
     */
    public function options(): Options
    {
        return $this->options;
    }
 }