<?php

/*
 * This file is part of justoverclock/newsletter.
 *
 * Copyright (c) 2024 Marco Colia.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Justoverclock\NewsLetter;

use Flarum\Extend;
use Justoverclock\NewsLetter\Controller\AddNewsLetterSubscriber;
use Justoverclock\NewsLetter\Controller\DeleteNewsLetterSubscriber;
use Justoverclock\NewsLetter\Controller\GetAllSubscriber;
use Justoverclock\NewsLetter\Controller\SendEmailController;
use Justoverclock\NewsLetter\Controller\SentNewsletters\GetLastSentNewsletter;
use Justoverclock\NewsLetter\Models\NewsLetterSubscriber;
use Justoverclock\NewsLetter\Serializer\NewsLetterSubscriberSerializer;


return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less')
        ->route('/newsletter/opt-out', 'justoverclock/newsletter'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\ApiSerializer(NewsLetterSubscriber::class))
        ->attributes(NewsLetterSubscriberSerializer::class),

    (new Extend\Routes('api'))
        ->get('/newsletter/subscribers', 'newsletter.getall', GetAllSubscriber::class)
        ->post('/newsletter/add', 'newsletter.add', AddNewsLetterSubscriber::class)
        ->delete('/newsletter/delete', 'newsletter.delete', DeleteNewsLetterSubscriber::class)
        ->post('/newsletter/sendall', 'newsletter.sendToAll', SendEmailController::class)
        ->get('/last-newsletter/get', 'lastnewsletter.get', GetLastSentNewsletter::class),

    (new Extend\View)
        ->namespace('justoverclock-newsletter', __DIR__.'/views'),
];
