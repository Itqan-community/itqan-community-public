<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Discussion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $discussion_id
 * @property string $language
 * @property string $translation
 * @property string $provider
 * @property bool $update_needed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DiscussionTranslation extends AbstractModel
{
    protected $table = 'discussion_translations';

    public $timestamps = true;

    protected $casts = [
        'update_needed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $fillable = [
        'discussion_id', 
        'language', 
        'translation', 
        'provider', 
        'update_needed'
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    public static function buildOrUpdate(int $discussionId, string $toLanguage, string $translated, string $engine): self
    {
        return self::updateOrCreate([
            'discussion_id' => $discussionId,
            'language' => $toLanguage,
        ], [
            'translation' => $translated,
            'provider' => $engine,
            'update_needed' => false,
            'updated_at' => Carbon::now()
        ]);
    }
}
