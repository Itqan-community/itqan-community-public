<?php

namespace Justoverclock\NewsLetter\Controller\SentNewsletters;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Flarum\User\Exception\NotAuthenticatedException;
use Justoverclock\NewsLetter\Models\SentNewsLetterModel;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class AddSentNewsletterEntry extends AbstractCreateController
{

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $attributes = $request->getParsedBody();
        $title = $attributes['newsletter_title'] ?? null;

        if (!$actor->isAdmin()) {
            throw new NotAuthenticatedException();
        }

        $sentNewsletter = new SentNewsLetterModel();
        $sentNewsletter->newsletter_title = $title;
        $sentNewsletter->save();

        return $sentNewsletter;
    }
}
