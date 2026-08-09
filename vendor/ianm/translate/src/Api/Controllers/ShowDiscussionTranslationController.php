<?php

namespace IanM\Translate\Api\Controllers;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use IanM\Translate\Api\Serializers\DiscussionTranslationSerializer;
use IanM\Translate\Model\DiscussionTranslation;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowDiscussionTranslationController extends AbstractShowTranslationController
{
    public $serializer = DiscussionTranslationSerializer::class;

    public function __construct(protected DiscussionRepository $discussions, protected TranslationProviderInterface $translator)
    {
    }

    protected function data(ServerRequestInterface $request, Document $document): DiscussionTranslation
    {
        $actor = RequestUtil::getActor($request);
        $params = $request->getQueryParams();
        $lang = Arr::get($params, 'lang');

        $this->validateLanguageSupported($lang, $actor);

        $discussion = $this->discussions->findOrFail(
            Arr::get($params, 'id'),
            $actor
        );

        if (!$discussion instanceof Discussion) {
            throw new ValidationException([
                'id' => 'Only discussion can be translated here.',
            ]);
        }

        $this->checkPermission($actor);

        return $this->translator->translateDiscussionTitle(
            $discussion, 
            $lang, 
            $discussion->user ?? $actor, 
            $this->shouldForce($params, $actor)
        );
    }
}
