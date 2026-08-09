<?php

namespace Irmmr\RTLCss\Process\Swap;

use Irmmr\RTLCss\Helpers;
use Irmmr\RTLCss\Manipulate;
use Sabberworm\CSS\Value\CSSFunction;
use Sabberworm\CSS\Value\RuleValueList;
use Sabberworm\CSS\Value\Size;

/**
 * class SwapBackground
 *
 * @package Irmmr\RTLCss
 */
class SwapBackground extends Swap
{
    /**
     * apply swap values
     *
     * @return void
     */
    public function apply(): void
    {
        $value = $this->value;

        // have multi backgrounds
        $is_multi_bg = false;

        if ($value instanceof RuleValueList) {
            $items = $value->getListComponents();
            $sep   = $value->getListSeparator();

            if ($sep != ',') {
                $items = [$value];
            } else {
                $is_multi_bg = true;
            }
        } else {
            $items = [$value];
        }

        // define first
        // $part_key = null;

        // every background seprated by ","
        foreach ($items as $item_key => $item) {
            // every loop of background
            $item = $items[ $item_key ];

            // have multi part every bg
            //$is_multi_part = false;

            // detect single background as a list of values/parts
            if ($item instanceof RuleValueList) {
                $parts = $item->getListComponents();
                //$is_multi_part = true;
            } else {
                $parts = [$item];
            }

            // get items string value: every bg as string
            $item_str = Helpers::getValueStr($item);

            // has any left or right entries in item
            $item_has_dir = preg_match('/\b(top|bottom|right|left)\b/i', $item_str);

            // has any top or bottom positions
            $has_top_bottom_pos = preg_match('/\b(top|bottom)\b/i', $item_str);

            // index of every part to check
            $part_i = 0;

            $requires_positional_argument   = false;
            $has_positional_argument        = false;

            // the times we lead to numbers
            $loop_part_num = 0;
            $sizes_count   = 0;
            $apply_syntax  = false;

            // first of all: get the numbers of sizes (search)
            if ($this->property !== 'background-position-x' && $this->property !== 'perspective-origin') {
                foreach ($parts as $part_s_key => $part_s) {
                    if ($part_s instanceof Size) {
                        $sizes_count ++;

                        if ($sizes_count === 1) {
                            if ($part_s->getUnit() != '%' && $part_s->getSize() != 0) {
                                $apply_syntax = true;
                            } else {
                                break;
                            }
                        }
                    }
                }
            }

            // search in every value entered in background entry: each value of every bg
            foreach ($parts as $part_key => $part) {
                // every entery/value of every background
                $part = $parts[ $part_key ];

                // increament part index
                $part_i ++;

                // get part as string
                $part_str = Helpers::getValueStr($part);

                if ($apply_syntax && $part instanceof Size && !$item_has_dir) {
                    $loop_part_num ++;

                    if ($sizes_count === 2) {
                        if ($loop_part_num === 1) {
                            $parts[ $part_key ] = sprintf('right %s', $part->__toString());
                        } elseif ($loop_part_num === 2) {
                            $parts[ $part_key ] = sprintf('top %s', $part->__toString());
                        }
                    } elseif ($sizes_count === 1) {
                        $parts[ $part_key ] = sprintf('right %s top 50%%', $part->__toString());
                    }
                }

                // do not ignore 2th right, left for perspective-origin
                if ($this->property === 'perspective-origin') {
                    $ignore_part = count($parts) === 2 && $part_i === 2 && !preg_match('/\b(right|left)\b/i', $part_str);
                } else {
                    $ignore_part = count($parts) === 2 && $part_i === 2 && !$has_top_bottom_pos;
                }

                if ($ignore_part) {
                    continue;
                }

                if (!is_object($part)) {
                    $flipped = Helpers::swapLeftRight($part);

                    $has_positional_argument      = $parts[ $part_key ] != $flipped;
                    $requires_positional_argument = true;

                    $parts[ $part_key ] = $flipped;

                    continue;

                } else if ($part instanceof CSSFunction && strpos($part->getName(), 'calc') !== false) {
                    Manipulate::complement($part);

                } else if ($part instanceof Size && ($part->getUnit() === '%' || !$part->getUnit())) {

                    // Is this a value we're interested in?
                    if (!$requires_positional_argument || $has_positional_argument) {
                        Manipulate::complement($part);
                        $part->setUnit('%');

                        // We only need to change one value.
                        break;
                    }

                }

                $has_positional_argument = false;
            }

            if ($is_multi_bg && $item instanceof RuleValueList) {
                $item->setListComponents($parts);
            } else {
                $items[ $item_key ] = implode(' ', $parts);
            }
        }

        if ($value instanceof RuleValueList) {
            $value->setListComponents($items);
        } else {
            $value = $items;
        }

        $this->rule->setValue( Helpers::getValueStr($value) );
    }
}