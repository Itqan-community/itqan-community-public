<?php

namespace Itqan\PreviewCards\Card;

class CardData
{
    public const TEMPLATE_VERSION = '8';

    public function __construct(
        public readonly int $discussionId,
        public readonly string $title,
        public readonly ?string $excerpt,
        public readonly ?string $authorName,
        public readonly ?string $avatarDataUri,
        public readonly ?string $tagName,
        public readonly string $createdAt,
        public readonly string $displayDate,
        public readonly int $replyCount,
        public readonly ?string $lastReplyAt,
        public readonly ?string $lastReplyDisplay,
        public readonly string $locale,
        public readonly string $direction,
        public readonly string $siteTitle,
        public readonly ?string $tagline,
        public readonly array $colors,
        public readonly ?string $logoDataUri,
        public readonly ?string $backgroundDataUri,
        public readonly array $elements,
    ) {
    }

    public function hashInput(): string
    {
        return implode('|', [
            (string) $this->discussionId,
            $this->title,
            (string) $this->excerpt,
            (string) $this->authorName,
            $this->avatarDataUri !== null ? sha1($this->avatarDataUri) : '',
            (string) $this->tagName,
            $this->createdAt,
            $this->displayDate,
            (string) $this->replyCount,
            (string) $this->lastReplyAt,
            (string) $this->lastReplyDisplay,
            $this->locale,
            $this->siteTitle,
            (string) $this->tagline,
            json_encode($this->colors) ?: '',
            $this->logoDataUri !== null ? sha1($this->logoDataUri) : '',
            $this->backgroundDataUri !== null ? sha1($this->backgroundDataUri) : '',
            json_encode($this->elements) ?: '',
            self::TEMPLATE_VERSION,
        ]);
    }
}
