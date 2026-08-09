<?php

namespace Irmmr\RTLCss\Process\Swap;

use Sabberworm\CSS\Value\RuleValueList;

/**
 * class SwapCursor
 *
 * @package Irmmr\RTLCss
 */
class SwapCursor extends Swap
{
    /**
     * apply swap values
     *
     * @return void
     */
    public function apply(): void
    {
        $value = $this->value;

        $has_list = false;
        $parts    = [$value];

        if ($value instanceof RuleValueList) {
            $has_list   = true;
            $parts      = $value->getListComponents();
        }

        foreach ($parts as $key => $part) {
            if (!is_object($part)) {
                $parts[$key] = preg_replace_callback('/\b(ne|nw|se|sw|nesw|nwse)-resize/', function($matches) {
                    return str_replace(
                        $matches[1],
                        str_replace(['e', 'w', '*'], ['*', 'e', 'w'],
                        $matches[1]),
                        $matches[0]
                    );
                }, $part);
            }
        }

        if ($has_list) {
            $value->setListComponents($parts);
        } else {
            $this->rule->setValue($parts[0]);
        }
    }
}