<?php

namespace Itqan\Notifications\Api;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use Flarum\Tags\TagRepository;
use Illuminate\Support\Arr;
use Itqan\Notifications\TagNotificationPreference;
use Psr\Http\Message\ServerRequestInterface;

class DeleteTagNotificationChannelController extends AbstractDeleteController
{
    /**
     * @var TagRepository
     */
    protected $tags;

    public function __construct(TagRepository $tags)
    {
        $this->tags = $tags;
    }

    protected function delete(ServerRequestInterface $request)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $tag = $this->tags->findOrFail(Arr::get($request->getQueryParams(), 'id'), $actor);

        TagNotificationPreference::deleteFor($actor, $tag);
    }
}
