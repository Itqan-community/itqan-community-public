<?php

namespace IanM\Translate\Api\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Database\AbstractModel;
use Flarum\Foundation\ValidationException;
use Flarum\User\Exception\PermissionDeniedException;
use Flarum\User\User;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

abstract class AbstractShowTranslationController extends AbstractShowController
{
    public function __construct(protected TranslationProviderInterface $translator)
    {
    }
    
    abstract protected function data(ServerRequestInterface $request, Document $document): AbstractModel;

    protected function validateLanguageSupported(string $lang, User $actor): void
    {
        if (!in_array($lang, $this->translator->supportedLanguages($actor))) {
            throw new ValidationException([$lang => 'Language not supported by the current configuration.']);
        }
    }

    protected function shouldForce(array $params, User $actor): bool
    {
        return Arr::get($params, 'refresh', false) && $actor->can('refreshTranslation');
    }

    protected function checkPermission(User $actor): void
    {
        if ($actor->cannot('translateLocale') && $actor->cannot('translateAll')) {
            throw new PermissionDeniedException();
        }
    }
}