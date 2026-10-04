<?php

namespace Itqan\PreviewCards\Card;

use Flarum\Http\UrlGenerator;
use Itqan\PreviewCards\Render\FontSet;

class CardUrlBuilder
{
    public function __construct(
        protected UrlGenerator $url,
        protected FontSet $fonts,
    ) {
    }

    public function hash(CardData $data): string
    {
        return substr(hash('sha256', $data->hashInput().'|'.$this->fonts->fingerprint()), 0, 12);
    }

    public function path(CardData $data): string
    {
        return '/og/d/'.$data->discussionId.'-'.$this->hash($data).'.png';
    }

    public function absolute(CardData $data): string
    {
        return $this->url->to('forum')->base().$this->path($data);
    }
}
