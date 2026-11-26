<?php

/*
 * This file is part of fof/moderator-warnings
 *
 * Copyright (c) Alexander Skvortsov.
 * Copyright (c) FriendsOfFlarum
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

use Flarum\Formatter\Formatter;
use FoF\ModeratorWarnings\Model\Warning;
use Illuminate\Container\Container;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $container = Container::getInstance();

        // Get the formatter from the container
        if (! $container->bound(Formatter::class)) {
            // If formatter is not available, skip migration
            return;
        }

        $formatter = $container->make(Formatter::class);

        Warning::chunkById(1000, function ($warnings) use ($formatter) {
            foreach ($warnings as $warning) {
                if ($warning->public_comment) {
                    $warning->public_comment = $formatter->parse($warning->public_comment);
                }
                if ($warning->private_comment) {
                    $warning->private_comment = $formatter->parse($warning->private_comment);
                }
                $warning->save();
            }
        });
    },

    'down' => function (Builder $schema) {
        // changes should be kept
    },
];
