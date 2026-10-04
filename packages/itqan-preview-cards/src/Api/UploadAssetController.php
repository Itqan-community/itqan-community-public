<?php

namespace Itqan\PreviewCards\Api;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class UploadAssetController implements RequestHandlerInterface
{
    protected const MAX_BYTES = 5242880;

    protected const EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
    ];

    protected string $settingKey = '';
    protected string $field = '';
    protected string $prefix = '';

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Container $container,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $file = Arr::get($request->getUploadedFiles(), $this->field);

        if (! $file instanceof UploadedFileInterface || $file->getError() !== UPLOAD_ERR_OK) {
            return (new Response())->withStatus(422);
        }

        $contents = $file->getStream()->getContents();
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (strlen($contents) === 0 || strlen($contents) > static::MAX_BYTES || ! isset(static::EXTENSIONS[$mime])) {
            return (new Response())->withStatus(422);
        }

        $disk = $this->container->make('filesystem')->disk('flarum-assets');
        $old = (string) $this->settings->get($this->settingKey, '');

        if ($old !== '' && $disk->exists($old)) {
            $disk->delete($old);
        }

        $path = 'itqan-preview-cards/'.$this->prefix.'-'.bin2hex(random_bytes(6)).'.'.static::EXTENSIONS[$mime];

        $disk->put($path, $contents);
        $this->settings->set($this->settingKey, $path);

        $response = new Response();
        $response->getBody()->write('{"ok":true}');

        return $response->withHeader('Content-Type', 'application/json');
    }
}
