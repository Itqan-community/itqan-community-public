<?php

namespace Irmmr\RTLCss\Process\Swap;

use Irmmr\RTLCss\Helpers;
use Irmmr\RTLCss\Manipulate;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Size;

/**
 * class SwapShadow
 *
 * @package Irmmr\RTLCss
 */
class SwapShadow extends Swap
{
    /**
     * apply swap values
     *
     * @return void
     */
    public function apply(): void
    {
        $value    = $this->value;
        $is_multi = false;

        if ($value instanceof RuleValueList) {
            $items = $value->getListComponents();
            $sep   = $value->getListSeparator();

            if ($sep != ',') {
                $items = [$value];
            } else {
                $is_multi = true;
            }
        } else {
            $items = [$value];
        }

        foreach ($items as $item_key => $item) {
            $item = $items[ $item_key ];

            if ($item instanceof RuleValueList) {
                $parts = $item->getListComponents();
            } else {
                Manipulate::negate($item);

                break;
            }

            foreach ($parts as $part_key => $part) {
                $part = $parts[ $part_key ];

                // just do it for first!
                if ($part instanceof Size) {
                    Manipulate::negate($part);

                    break;
                }
            }
        }

        if ($is_multi) {
            $value->setListComponents($items);
        } else {
            $value = $items;
        }

        $this->rule->setValue( Helpers::getValueStr($value) );
    }
}