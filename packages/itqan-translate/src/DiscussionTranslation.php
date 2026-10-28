<?php

namespace Itqan\Translate;

use Flarum\Database\AbstractModel;

/**
 * Existing ianm/translate cache table (kept after the extension was removed).
 *
 * @property int $discussion_id
 * @property string $language
 * @property string $translation
 * @property string $provider
 * @property bool $update_needed
 */
class DiscussionTranslation extends AbstractModel
{
    protected $table = 'discussion_translations';

    public $timestamps = true;

    protected $guarded = [];
}
