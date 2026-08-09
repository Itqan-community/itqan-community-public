<?php

namespace Justoverclock\NewsLetter\Serializer;

use Justoverclock\NewsLetter\Models\NewsLetterSubscriber;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\AbstractSerializer;

class NewsLetterSubscriberSerializer extends AbstractSerializer
{
    protected $type = 'newsletter_subscribers_add';

    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    public function getId($model)
    {
        return $model->id; // This could be a primary key or any other identifier
    }

    public function getAttributes($model, array $fields = null): array
    {
        /** @var NewsLetterSubscriber $model */
        return [
            'email' => $model->email
        ];
    }
}
