<?php

namespace Justoverclock\NewsLetter\Controller;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Settings\SettingsRepositoryInterface;
use Justoverclock\NewsLetter\Models\NewsLetterSubscriber;
use Justoverclock\NewsLetter\Serializer\NewsLetterSubscriberSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tobscure\JsonApi\Document;

class AddNewsLetterSubscriber extends AbstractCreateController
{
    protected TranslatorInterface $translator;
    protected SettingsRepositoryInterface $settings;

    public $serializer = NewsLetterSubscriberSerializer::class;

    public function __construct(
        TranslatorInterface $translator,
        SettingsRepositoryInterface $settings,
    )
    {
        $this->settings = $settings;
        $this->translator = $translator;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $attributes = $request->getParsedBody();
        $email = $attributes['email'] ?? null;

        if (!$email) {
            $emailRequired = $this->translator->trans('justoverclock-newsletter.forum.emailRequired');
            throw new BadRequestException($emailRequired);
        }

        if (NewsLetterSubscriber::emailExists($email)) {
            $emailExist = $this->translator->trans('justoverclock-newsletter.forum.subscriberExist');
            throw new BadRequestException($emailExist);
        }

        $subscriber = new NewsLetterSubscriber();
        $subscriber->email = $email;
        $subscriber->save();

        return $subscriber;
    }
}
