<?php

namespace Itqan\Discussions\Provider;

use Flarum\Foundation\AbstractServiceProvider;

/**
 * Teaches the server the two new sort keys.
 *
 * Flarum keeps two sort maps: one in the frontend state, and one in the
 * container that `Forum\Content\Index` uses to build the API document embedded
 * in the first page. Extending only the frontend map looks right in the
 * dropdown and does nothing on a direct load or a refresh — the preloaded
 * document already answers the request, so no API call is ever made and the
 * list comes back in the default order.
 */
class SortMapProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->extend('flarum.forum.discussions.sortmap', function (array $map) {
            // Repointed, matching the frontend: on this forum "top" means
            // highest-voted, not most-commented.
            $map['top'] = '-votes';
            $map['hot'] = '-hotness';

            return $map;
        });
    }
}
