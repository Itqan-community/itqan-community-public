<?php

namespace Itqan\Notifications\Api;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Locale\Translator;
use Flarum\Tags\Api\Serializer\TagSerializer;
use Flarum\Tags\TagRepository;
use Illuminate\Support\Arr;
use Itqan\Notifications\TagNotificationPreference;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class SetTagNotificationChannelController extends AbstractShowController
{
    public $serializer = TagSerializer::class;

    /**
     * @var TagRepository
     */
    protected $tags;

    /**
     * @var Translator
     */
    protected $translator;

    public function __construct(TagRepository $tags, Translator $translator)
    {
        $this->tags = $tags;
        $this->translator = $translator;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $tag = $this->tags->findOrFail(Arr::get($request->getQueryParams(), 'id'), $actor);

        $channel = Arr::get($request->getParsedBody(), 'data.attributes.channel');

        if (! is_string($channel) || $channel === '') {
            throw new ValidationException([
                'channel' => $this->translator->trans('itqan-notifications.api.channel_required'),
            ]);
        }

        if (! TagNotificationPreference::isValidChannel($channel)) {
            throw new ValidationException([
                'channel' => $this->translator->trans('itqan-notifications.api.invalid_channel'),
            ]);
        }

        $preference = TagNotificationPreference::saveFor($actor, $tag, $channel);
        $tag->setRelation(TagNotificationPreference::RELATION, $preference);

        return $tag;
    }
}
