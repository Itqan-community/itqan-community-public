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
use Flarum\Post\CommentPost;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $post_id
 * @property string $language
 * @property string $content
 * @property string $provider
 * @property bool $update_needed
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PostTranslation extends AbstractModel
{
    protected $table = 'post_translations';

    public $timestamps = true;

    protected $casts = [
        'update_needed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    protected $fillable = [
        'post_id',
        'language',
        'content',
        'provider',
        'update_needed',
        'updated_at'
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(CommentPost::class);
    }

    public static function buildOrUpdate(int $postId, string $toLanguage, string $translated, string $engine): self
    {
        return PostTranslation::updateOrCreate([
            'post_id' => $postId,
            'language' => $toLanguage,
        ], [
            'content' => $translated,
            'provider' => $engine,
            'update_needed' => false,
            'updated_at' => Carbon::now()
        ]);
    }
}
