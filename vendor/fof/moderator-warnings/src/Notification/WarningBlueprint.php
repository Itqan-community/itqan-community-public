<?php

/*
 * This file is part of fof/moderator-warnings
 *
 * Copyright (c) Alexander Skvortsov.
 * Copyright (c) FriendsOfFlarum
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace FoF\ModeratorWarnings\Notification;

use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use FoF\ModeratorWarnings\Model\Warning;
use Symfony\Contracts\Translation\TranslatorInterface;

class WarningBlueprint implements BlueprintInterface, MailableInterface
{
    /**
     * @var Warning
     */
    public $warning;

    public function __construct(Warning $warning)
    {
        $this->warning = $warning;
    }

    /**
     * {@inheritdoc}
     */
    public function getSubject()
    {
        return $this->warning;
    }

    /**
     * {@inheritdoc}
     */
    public function getFromUser()
    {
        return $this->warning->addedByUser;
    }

    /**
     * {@inheritdoc}
     */
    public function getData()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getEmailView()
    {
        return ['text' => 'fof-moderator-warnings::emails.warning'];
    }

    /**
     * {@inheritdoc}
     */
    public function getEmailSubject(TranslatorInterface $translator)
    {
        return $translator->trans($this->getTranslation().'.subject', [
            '{warner_display_name}' => $this->warning->addedByUser->display_name,
            '{strikes}' => $this->warning->strikes,
            '{discussion_title}' => $this->warning->post ? $this->warning->post->discussion->title : '',
        ]);
    }

    public function getTranslation()
    {
        return 'fof-moderator-warnings.emails.'.($this->warning->post_id ? 'post_warned' : 'user_warned');
    }

    public function getUnparsedComment()
    {
        return Warning::getFormatter()->unparse($this->warning->public_comment);
    }

    /**
     * {@inheritdoc}
     */
    public static function getType()
    {
        return 'warning';
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubjectModel()
    {
        return Warning::class;
    }
}
