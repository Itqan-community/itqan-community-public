<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate\Api\Controllers;

use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Post\CommentPost;
use Flarum\Post\PostRepository;
use IanM\Translate\Api\Serializers\PostTranslationSerializer;
use IanM\Translate\Model\PostTranslation;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowPostTranslationController extends AbstractShowTranslationController
{
    public $serializer = PostTranslationSerializer::class;

    public function __construct(protected PostRepository $posts, protected TranslationProviderInterface $translator)
    {
        parent::__construct($translator);
    }

    protected function data(ServerRequestInterface $request, Document $document): PostTranslation
    {
        $actor = RequestUtil::getActor($request);
        $params = $request->getQueryParams();
        $lang = Arr::get($params, 'lang');

        $this->validateLanguageSupported($lang, $actor);

        $post = $this->posts->findOrFail(
            Arr::get($params, 'id'),
            $actor
        );

        if (!$post instanceof CommentPost) {
            throw new ValidationException([
                'id' => 'Only comment posts can be translated.',
            ]);
        }

        $this->checkPermission($actor);

        return $this->translator->translatePostContent(
            $post, 
            $lang, 
            $post->user ?? $actor, 
            $this->shouldForce($params, $actor)
        );
    }
}
