<?php

namespace Itqan\Llms\ServiceProvider;

use Flarum\Foundation\AbstractServiceProvider;
use Itqan\Llms\Markdown\DiscussionRenderer;
use Itqan\Llms\Markdown\HtmlToMarkdown;
use Itqan\Llms\Markdown\ThreadTree;
use Itqan\Llms\Support\ForumUrls;

class LlmServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->singleton(HtmlToMarkdown::class, fn () => new HtmlToMarkdown);

        $this->container->singleton(ThreadTree::class, fn () => new ThreadTree);

        $this->container->singleton(ForumUrls::class, fn ($container) => new ForumUrls(
            $container->make(\Flarum\Http\UrlGenerator::class),
            $container->make(\Flarum\Http\SlugManager::class),
        ));

        $this->container->singleton(DiscussionRenderer::class, fn ($container) => new DiscussionRenderer(
            $container->make(HtmlToMarkdown::class),
            $container->make(ThreadTree::class),
            $container->make(ForumUrls::class),
        ));
    }
}
