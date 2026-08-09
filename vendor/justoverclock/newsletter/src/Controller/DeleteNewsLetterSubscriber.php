<?php

namespace Justoverclock\NewsLetter\Controller;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;
use Justoverclock\NewsLetter\Models\NewsLetterSubscriber;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Contracts\Translation\TranslatorInterface;

class DeleteNewsLetterSubscriber extends AbstractDeleteController
{
    protected TranslatorInterface $translator;
    protected SettingsRepositoryInterface $settings;

    public function __construct(
        TranslatorInterface $translator,
        SettingsRepositoryInterface $settings,
    )
    {
        $this->settings = $settings;
        $this->translator = $translator;
    }

    protected function delete(ServerRequestInterface $request)
    {
        $emailToDelete = trim(Arr::get($request->getQueryParams(), 'email'));

        if (!$emailToDelete) {
            $emailRequired = $this->translator->trans('justoverclock-newsletter.forum.emailRequired');
            throw new BadRequestException($emailRequired);
        }

        $deleted = NewsLetterSubscriber::deleteByEmail($emailToDelete);

        if (!$deleted) {
            $subscriberNotFoundMessage = $this->translator->trans('justoverclock-newsletter.forum.emailNotFound');
            throw new BadRequestException($subscriberNotFoundMessage);
        }

        return [
            'message' => $this->translator->trans('justoverclock-newsletter.forum.subscriberDeleted'),
        ];
    }
}
