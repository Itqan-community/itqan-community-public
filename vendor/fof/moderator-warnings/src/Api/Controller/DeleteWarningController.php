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

namespace FoF\ModeratorWarnings\Api\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Flarum\Notification\NotificationSyncer;
use FoF\ModeratorWarnings\Api\Serializer\WarningSerializer;
use FoF\ModeratorWarnings\Model\Warning;
use FoF\ModeratorWarnings\Notification\WarningBlueprint;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class DeleteWarningController extends AbstractCreateController
{
    public $serializer = WarningSerializer::class;

    /**
     * @var NotificationSyncer
     */
    protected $notifications;

    /**
     * @param NotificationSyncer $notifications
     */
    public function __construct(NotificationSyncer $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * {@inheritdoc}
     */
    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertCan('user.deleteWarnings');

        $warning = Warning::find(Arr::get($request->getQueryParams(), 'warning_id'));

        $warning->delete();

        $this->notifications->sync(new WarningBlueprint($warning), []);

        return $warning;
    }
}
