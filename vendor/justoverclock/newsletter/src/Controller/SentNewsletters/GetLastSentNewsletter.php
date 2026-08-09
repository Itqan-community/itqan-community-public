<?php

namespace Justoverclock\NewsLetter\Controller\SentNewsletters;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\NotAuthenticatedException;
use Justoverclock\NewsLetter\Models\SentNewsLetterModel;
use Justoverclock\NewsLetter\Serializer\SentNewsletterSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class GetLastSentNewsletter extends AbstractListController
{

    public $serializer = SentNewsletterSerializer::class;

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        if (!$actor->isAdmin()) {
            throw new NotAuthenticatedException();
        }

        return SentNewsLetterModel::query()
            ->orderBy('id', 'desc')
            ->limit(1)
            ->get();
    }
}
