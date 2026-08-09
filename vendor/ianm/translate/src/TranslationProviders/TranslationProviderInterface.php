<?php

/*
 * This file is part of ianm/translate.
 *
 * Copyright (c) 2022 IanM.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace IanM\Translate\TranslationProviders;

use Flarum\Discussion\Discussion;
use Flarum\Post\CommentPost;
use Flarum\User\User;
use IanM\Translate\Model\PostTranslation;
use IanM\Translate\Model\DiscussionTranslation;

interface TranslationProviderInterface
{    
    /**
     * The name of the translation provider.
     *
     * @return string
     */
    public function name(): string;

    public function options(): array;

    /**
     * The URL to show in extensions settings to provide help or instructions to signup for the service.
     *
     * @return string
     */
    public function link(): string;

    /**
     * Indicates if the translation provider is ready to be used.
     *
     * @return boolean
     */
    public function isReady(): bool;

    /**
     * Identify the language of the content.
     *
     * @param CommentPost $post
     * @return string|null
     */
    public function identifyLanguage(CommentPost $post): ?string;

    public function identifyTitleLanguage(Discussion $discussion): ?string;

    /**
     * List of languages supported by the translation provider, filtered down to languages also enabled in Flarum.
     *
     * @param User $actor
     * @return array
     */
    public function supportedLanguages(User $actor): array;

    /**
     * Begin the translation process. This will extract the unparsed content for better compatibility in retaining any formatting. 
     * The post formatter is used to parse/unparse the content.
     *
     * @param CommentPost $post
     * @param string $toLanguage
     * @param User $user
     * @param boolean $force
     * @return PostTranslation
     */
    public function translatePostContent(CommentPost $post, string $toLanguage, User $user, bool $force = false): PostTranslation;

    public function translateDiscussionTitle(Discussion $discussion, string $toLanguage, User $user, bool $force = false): DiscussionTranslation;

    public function enabled(): bool;
}
