<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate\Api\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use IanM\Translate\Model\DiscussionTranslation;

class DiscussionTranslationSerializer extends AbstractSerializer
{
    protected $type = 'discussion-translation';
    
    /**
     * @param DiscussionTranslation $model
     *
     * @return array
     */
    protected function getDefaultAttributes($model): array
    {
        return [
            'discussionId' => $model->discussion_id,
            'language' => $model->language,
            'translation' => $model->translation,
            'provider' => $model->provider,
            'createdAt' => $this->formatDate($model->created_at),
            'updatedAt' => $this->formatDate($model->updated_at)
        ];
    }

    public function getId($model)
    {
        return $model->discussion_id . $model->language;
    }
}
