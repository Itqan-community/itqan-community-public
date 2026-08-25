<?php

namespace Itqan\Typography\Content;

use Flarum\Frontend\Document;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Removes the Google Fonts tags from the document head.
 *
 * This is not tidying. Those tags are added through HTML Head, which appends to
 * the head *after* the stylesheet links — so Google's `@font-face` for
 * "Noto Sans Arabic" is declared last and wins the cascade over the one this
 * extension ships. Left in place, the self-hosted font is never used and the
 * page still waits on two round trips to a third party before any Arabic text
 * can be set in its own typeface.
 *
 * Deleting the entry from Administration → HTML Head makes this a no-op, and
 * that is the tidier place for it to end. This runs regardless so that
 * enabling the extension is enough to fix the loading path.
 */
class DropRemoteFontLinks
{
    const HOSTS = ['fonts.googleapis.com', 'fonts.gstatic.com'];

    public function __invoke(Document $document, Request $request)
    {
        $document->head = array_values(array_filter($document->head, function ($tag) {
            return ! $this->isRemoteFontTag($tag);
        }));
    }

    protected function isRemoteFontTag($tag): bool
    {
        if (! is_string($tag)) {
            return false;
        }

        foreach (self::HOSTS as $host) {
            if (stripos($tag, $host) !== false) {
                return true;
            }
        }

        return false;
    }
}
