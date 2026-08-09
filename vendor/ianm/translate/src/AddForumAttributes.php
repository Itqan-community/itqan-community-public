<?php

namespace IanM\Translate;

use Flarum\Api\Serializer\ForumSerializer;
use IanM\Translate\TranslationProviders\TranslationProviderInterface;

class AddForumAttributes
{
    public function __construct(
        protected TranslationProviderInterface $provider,
    ) {}
    
    public function __invoke(ForumSerializer $serializer, $model, array $attributes): array
    {
        $attributes['ianm-translate.supportedLanguages'] = $this->provider->supportedLanguages($serializer->getActor());
        
        return $attributes;
    }
}
