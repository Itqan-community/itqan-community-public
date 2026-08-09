<?php

namespace Irmmr\RTLCss\Process\Swap;

use Sabberworm\CSS\Value\RuleValueList;

/**
 * class SwapBorderRadius
 *
 * @package Irmmr\RTLCss
 */
class SwapBorderRadius extends Swap
{
    /**
     * apply swap values
     *
     * @return void
     */
    public function apply(): void
    {
        $value = $this->value;

        if ($value instanceof RuleValueList) {
            // Border radius can contain two lists separated by a slash.
            $groups = $value->getListComponents();

            if ($value->getListSeparator() !== '/') {
                $groups = [$value];
            }

            foreach ($groups as $group) {
                $values = $group->getListComponents();

                switch (count($values)) {
                    case 2:
                        $group->setListComponents(array_reverse($values));
                        break;

                    case 3:
                        $group->setListComponents([$values[1], $values[0], $values[1], $values[2]]);
                        break;

                    case 4:
                        $group->setListComponents([$values[1], $values[0], $values[3], $values[2]]);
                        break;
                }
            }
        }
    }
}