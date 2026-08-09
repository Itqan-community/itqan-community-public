<?php

namespace Bendary\AdminAudit\Middlewares;

use Illuminate\Contracts\Container\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CaptureRequestMiddleware implements MiddlewareInterface
{
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Bind the current request into the container so events fired downstream
        // (like Saving, Enabled, Disabled) can retrieve it and extract the actor/IP.
        $this->container->instance('audit.current_request', $request);

        return $handler->handle($request);
    }
}
