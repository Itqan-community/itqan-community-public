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

use Flarum\Api\Serializer\CurrentUserSerializer;
use Flarum\User\User;

class AddCurrentUserAttributes
{
    public function __invoke(CurrentUserSerializer $serializer, User $user, array $attributes): array
    {
        $attributes['canForceRefreshTranslations'] = $user->can('refreshTranslation');

        return $attributes;
    }
}
