<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate;

use Flarum\Api\Serializer\BasicPostSerializer;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;

class AddCommentPostAttributes
{
    /**
     * @var TranslationProviderInterface
     */
    protected $contentTranslator;

    public function __construct(TranslationProviderInterface $contentTranslator)
    {
        $this->contentTranslator = $contentTranslator;
    }
    
    public function __invoke(BasicPostSerializer $serializer, Post $post, array $attributes): array
    {
        if ($post instanceof CommentPost) {
            $attributes['detectedLang'] = $post->detected_lang;
        }
        
        return $attributes;
    }
}
