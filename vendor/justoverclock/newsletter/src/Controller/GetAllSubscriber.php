<?php

namespace Justoverclock\NewsLetter\Controller;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use Justoverclock\NewsLetter\Models\NewsLetterSubscriber;
use Justoverclock\NewsLetter\Serializer\NewsLetterSubscriberSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class GetAllSubscriber extends AbstractListController
{

    public $serializer = NewsLetterSubscriberSerializer::class;
    /**
     * Retrieve all subscribers from the database.
     */
    protected function data(ServerRequestInterface $request, Document $document): array
    {
        /** @var User $actor */
        $actor = RequestUtil::getActor($request);

        if (!$actor->isAdmin()) {
            throw new PermissionDeniedException('You are not authorized!');
        }

        $subscribers = NewsLetterSubscriber::all();
        return $subscribers->pluck('email')->toArray();
    }
}
