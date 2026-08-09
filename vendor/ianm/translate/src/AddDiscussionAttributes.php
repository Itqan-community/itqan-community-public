<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate;

use Flarum\Api\Serializer\DiscussionSerializer;
use Flarum\Discussion\Discussion;

class AddDiscussionAttributes
{
    public function __invoke(DiscussionSerializer $serializer, Discussion $discussion, array $attributes): array
    {
        $actor = $serializer->getActor();
        $attributes['canTranslate'] = $actor->can('translateLocale', $discussion) || $actor->can('translateAllFor', $discussion);
        $attributes['detectedLang'] = $discussion->detected_lang;
        
        return $attributes;
    }
}
