<?php

namespace Itqan\PreviewCards\Api;

use Flarum\Http\RequestUtil;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Container\Container;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class DeleteAssetController implements RequestHandlerInterface
{
    protected string $settingKey = '';

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Container $container,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $disk = $this->container->make('filesystem')->disk('flarum-assets');
        $old = (string) $this->settings->get($this->settingKey, '');

        if ($old !== '' && $disk->exists($old)) {
            $disk->delete($old);
        }

        $this->settings->set($this->settingKey, '');

        $response = new Response();
        $response->getBody()->write('{"ok":true}');

        return $response->withHeader('Content-Type', 'application/json');
    }
}
