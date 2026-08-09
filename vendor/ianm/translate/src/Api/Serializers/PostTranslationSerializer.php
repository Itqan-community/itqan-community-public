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
use Flarum\Formatter\Formatter;
use IanM\Translate\Model\PostTranslation;

class PostTranslationSerializer extends AbstractSerializer
{
    protected $type = 'post-translation';
    
    /**
     * @param PostTranslation $model
     *
     * @return array
     */
    protected function getDefaultAttributes($model): array
    {
        /** @var Formatter $formatter */
        $formatter = $model->post->getFormatter();
        
        return [
            'postId' => $model->post_id,
            'language' => $model->language,
            'translation' => $model->content,
            'translationHtml' => $formatter->render($model->content, $model->post, $this->request),
            'provider' => $model->provider,
            'createdAt' => $this->formatDate($model->created_at),
            'updatedAt' => $this->formatDate($model->updated_at)
        ];
    }

    public function getId($model)
    {
        return $model->post_id . $model->language;
    }
}
