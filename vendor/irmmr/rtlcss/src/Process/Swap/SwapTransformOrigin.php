<?php

namespace Irmmr\RTLCss\Process\Swap;

use Irmmr\RTLCss\Helpers;
use Irmmr\RTLCss\Manipulate;
use Sabberworm\CSS\Value\CSSFunction;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Size;

/**
 * class SwapTransformOrigin
 *
 * @package Irmmr\RTLCss
 */
class SwapTransformOrigin extends Swap
{
    /**
     * apply swap values
     *
     * @return void
     */
    public function apply(): void
    {
        $value = $this->value;

        $found_left_or_right = false;

        // Search for left or right.
        $parts = [$value];

        if ($value instanceof RuleValueList) {
            $parts = $value->getListComponents();
        }

        foreach ($parts as $key => $part) {
            if (!is_object($part) && preg_match('/left|right/i', $part)) {
                $found_left_or_right   = true;
                $parts[$key]           = Helpers::swapLeftRight($part);
            }
        }

        if ($found_left_or_right) {
            // We need to reconstruct the value because left/right are not represented by an object.
            $list = new RuleValueList(' ');

            $list->setListComponents($parts);

            $this->rule->setValue($list);
        } else {
            $value = $parts[0];

            // The first value may be referencing top or bottom (y instead of x).
            if (!is_object($value) && preg_match('/top|bottom/i', $value)) {
                $value = $parts[1];
            }

            // Flip the value.
            if ($value instanceof Size) {
                if ($value->getSize() == 0) {
                    $value->setSize(100);
                    $value->setUnit('%');

                } else if ($value->getUnit() === '%') {
                    Manipulate::complement($value);
                }
            } else if ($value instanceof CSSFunction && strpos($value->getName(), 'calc') !== false) {
                Manipulate::complement($value);
            }
        }
    }
}