<?php

namespace Itqan\Discussions\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use Itqan\Discussions\Access\PostVisibility;

/**
 * `PostPolicy` and the pre-serialization hook must share ONE `PostVisibility`,
 * or the hook fills a memo the policy never reads. An unbound class would be
 * a new instance on every resolve, so it is bound as a singleton.
 */
class PostVisibilityProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->singleton(PostVisibility::class);
    }
}
