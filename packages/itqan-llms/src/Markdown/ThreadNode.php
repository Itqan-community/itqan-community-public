<?php

namespace Itqan\Llms\Markdown;

/**
 * One node of a resolved reply tree.
 */
class ThreadNode
{
    /**
     * @var \Flarum\Post\Post
     */
    public $post;

    /**
     * Depth from the opening post. The opening post is 0.
     */
    public int $depth = 0;

    /**
     * @var ThreadNode[]
     */
    public array $children = [];

    public function __construct($post, int $depth = 0)
    {
        $this->post = $post;
        $this->depth = $depth;
    }
}
