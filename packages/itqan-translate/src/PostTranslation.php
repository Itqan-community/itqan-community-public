<?php

namespace Itqan\Translate;

use Flarum\Database\AbstractModel;

/**
 * Existing ianm/translate cache table (kept after the extension was removed).
 *
 * @property int $post_id
 * @property string $language
 * @property string|null $content
 * @property string $provider
 * @property bool $update_needed
 */
class PostTranslation extends AbstractModel
{
    protected $table = 'post_translations';

    public $timestamps = true;

    protected $guarded = [];
}
