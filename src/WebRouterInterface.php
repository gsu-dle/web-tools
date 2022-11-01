<?php

declare(strict_types=1);

namespace GAState\Tools\Web;


use Psr\Http\Message\ServerRequestInterface;
use Throwable;


interface WebRouterInterface
{
    /**
     * @return void
     */
    public function initRouter(): void;


    /**
     * @param ServerRequestInterface $request
     * 
     * @return WebHandlerInterface
     */
    public function getWebHandler(ServerRequestInterface $request): WebHandlerInterface;


    /**
     * @param ServerRequestInterface $request
     * @param Throwable $error
     * 
     * @return WebHandlerInterface
     */
    public function getWebErrorHandler(
        ServerRequestInterface $request,
        Throwable $error
    ): WebHandlerInterface;
}
