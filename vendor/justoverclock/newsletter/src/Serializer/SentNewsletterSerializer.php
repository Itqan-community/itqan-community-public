<?php

namespace Justoverclock\NewsLetter\Serializer;

use Carbon\Carbon;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\AbstractSerializer;

class SentNewsletterSerializer extends AbstractSerializer
{
    protected $type = 'sent_newsletters';

    /**
     * Get the default attributes for the model.
     *
     * @param mixed $model
     * @param array|null $fields
     * @return array
     */

    public function setRequest(ServerRequestInterface $request)
    {
        $this->request = $request;
    }

    protected function formatDate($date)
    {
        if ($date instanceof Carbon) {
            return $date->toIso8601String();
        }

        return null;
    }

    public function getAttributes($model, array $fields = null)
    {
        return [
            'id' => $model->id,
            'title' => $model->newsletter_title,
            'createdAt' => $this->formatDate($model->created_at),
            'updatedAt' => $this->formatDate($model->updated_at),
        ];
    }
}
