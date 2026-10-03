<?php

namespace Mtareq\NestedReplies\Provider;

use Flarum\Foundation\AbstractServiceProvider;

/**
 * Teaches the server the new sort keys.
 *
 * Flarum keeps two sort maps: one in the frontend state and one in the
 * container that `Forum\Content\Index` uses to build the API document embedded
 * in the first page. Extending only the frontend map looks right in the
 * dropdown and does nothing on a direct load or refresh — the preloaded
 * document answers the request, so no API call is made and the list returns in
 * the default order.
 *
 * `votes` and `replies` mirror the frontend catalog. `top` is kept as a
 * backend-only alias (`-votes`) so links that predate the catalog
 * (`?sort=top`) keep resolving to the highest-voted list. `hot`/hotness is
 * retired and deliberately absent.
 */
class SortMapProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->extend('flarum.forum.discussions.sortmap', function (array $map) {
            $map['votes'] = '-votes';
            $map['replies'] = '-commentCount';
            $map['top'] = '-votes';

            // Defensive: the retired `itqan-discussions` extension still leaves
            // its `hot` sort key in the container map when the community
            // vendor copy lingers; drop it so it cannot win over our `votes`.
            unset($map['hot']);

            return $map;
        });
    }
}
