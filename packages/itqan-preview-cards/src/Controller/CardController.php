<?php

namespace Itqan\PreviewCards\Controller;

use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Itqan\PreviewCards\Cache\CardCache;
use Itqan\PreviewCards\Cache\RenderLock;
use Itqan\PreviewCards\Card\CardFactory;
use Itqan\PreviewCards\Card\CardUrlBuilder;
use Itqan\PreviewCards\Render\CardRenderer;
use Itqan\PreviewCards\Render\CardTemplate;
use Itqan\PreviewCards\Render\FallbackCards;
use Itqan\PreviewCards\Settings;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

class CardController implements RequestHandlerInterface
{
    private const PNG_MAGIC = "\x89PNG\r\n\x1a\n";

    public function __construct(
        protected Settings $settings,
        protected CardFactory $factory,
        protected CardUrlBuilder $urls,
        protected CardCache $cache,
        protected CardTemplate $template,
        protected CardRenderer $renderer,
        protected FallbackCards $fallbacks,
        protected RenderLock $lock,
        protected LoggerInterface $logger,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $params = $request->getQueryParams();
        $id = (int) ($params['id'] ?? 0);
        $requestedHash = (string) ($params['hash'] ?? '');

        $discussion = $id > 0 ? Discussion::whereVisibleTo($actor)->find($id) : null;

        if (! $discussion) {
            return $this->image($this->fallbacks->png(), 300, 'brand');
        }

        $locale = $this->factory->localeFor($discussion);
        $data = $this->factory->forDiscussion($discussion, $locale);
        $hash = $this->urls->hash($data);

        if ($requestedHash === $hash && ($bytes = $this->cache->read($id, $hash)) !== null) {
            return $this->immutable($bytes, $hash, $request);
        }

        $handles = $this->lock->acquire($id.':'.$hash);

        if ($handles === null) {
            $cached = $this->cache->read($id, $hash);

            if ($cached !== null) {
                return $this->immutable($cached, $hash, $request);
            }

            return $this->image($this->fallbacks->png($locale), $this->settings->provisionalTtl(), 'provisional');
        }

        try {
            $png = $this->renderer->render(
                $this->template->forDiscussion($data),
                CardTemplate::WIDTH,
                CardTemplate::HEIGHT,
            );

            if (! str_starts_with($png, self::PNG_MAGIC)) {
                throw new \RuntimeException('Renderer returned a non-PNG payload.');
            }

            $this->cache->store($id, $hash, $png);
        } catch (\Throwable $e) {
            $this->logger->warning('[itqan-preview-cards] Card render failed for discussion '.$id.': '.$e->getMessage());

            return $this->image($this->fallbacks->png($locale), $this->settings->provisionalTtl(), 'provisional');
        } finally {
            $this->lock->release($handles);
        }

        return $this->immutable($png, $hash, $request);
    }

    private function immutable(string $bytes, string $hash, ServerRequestInterface $request): ResponseInterface
    {
        $etag = '"'.$hash.'"';

        if (trim($request->getHeaderLine('If-None-Match')) === $etag) {
            return (new Response())
                ->withStatus(304)
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
        }

        return $this->image($bytes, 31536000, 'generated')
            ->withHeader('ETag', $etag)
            ->withHeader('Cache-Control', 'public, max-age=31536000, immutable');
    }

    private function image(string $bytes, int $ttl, string $source): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write($bytes);

        return $response
            ->withHeader('Content-Type', 'image/png')
            ->withHeader('Content-Length', (string) strlen($bytes))
            ->withHeader('Cache-Control', 'public, max-age='.max(0, $ttl))
            ->withHeader('X-Itqan-Card', $source);
    }
}
