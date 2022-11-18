<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Aura\Router\Matcher;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Closure;
use Exception;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;


abstract class WebRouter implements WebRouterInterface
{
    /**
     * @param array<string,string> $env
     * @param RouterContainer $router
     * @param WebHandlerFactory $webHandlerFactory
     */
    public function __construct(
        protected array $env,
        protected LoggerInterface $logger,
        protected RouterContainer $router,
        protected WebHandlerFactory $webHandlerFactory
    ) {
        $errorHandler = $env['WEB_ERROR_HANDLER'] ?? WebErrorHandler::class;
        if ($errorHandler === '') {
            throw new Exception('WEB_ERROR_HANDLER is not defined');
        }
        if (!is_subclass_of($errorHandler, WebHandlerInterface::class)) {
            throw new Exception("WEB_ERROR_HANDLER is not of type WebHandlerInterface: {$errorHandler}");
        }
    }


    /**
     * @return void
     */
    abstract public function initRouter(): void;


    /**
     * @param ServerRequestInterface $request
     * 
     * @return WebHandlerInterface
     */
    public function getWebHandler(ServerRequestInterface &$request): WebHandlerInterface
    {
        // Match the request to a route
        $matcher = $this->router->getMatcher();
        $route = $matcher->match($request);
        if (!$route instanceof Route) {
            $this->logger->debug('No matching route found for ' . $request->getUri()->getPath());
            throw $this->handleNoRoute($matcher);
        }

        if (!(is_string($route->handler) || $route->handler instanceof Closure)) {
            throw new Exception('Invalid type for route handler');
        }

        // Add any route attributes to the request
        foreach ($route->attributes as $key => $val) {
            $request = $request->withAttribute($key, $val);
        }

        return $this->webHandlerFactory->createWebHandler($route->handler);
    }


    /**
     * @param ServerRequestInterface $request
     * @param Throwable $error
     * 
     * @return WebHandlerInterface
     */
    public function getWebErrorHandler(
        ServerRequestInterface &$request,
        Throwable $error
    ): WebHandlerInterface {
        return $this->webHandlerFactory->createWebHandler($this->env['WEB_ERROR_HANDLER'] ?? WebErrorHandler::class);
    }


    /**
     * @param Matcher $matcher
     * 
     * @return Throwable
     */
    protected function handleNoRoute(Matcher $matcher): Throwable
    {
        switch ($matcher->getFailedRoute()?->failedRule) {
            case \Aura\Router\Rule\Allows::class:
                return new WebError(WebResponseCode::METHOD_NOT_ALLOWED);
            case \Aura\Router\Rule\Accepts::class:
                return new WebError(WebResponseCode::NOT_ACCEPTABLE);
            default:
                return new WebError(WebResponseCode::NOT_FOUND);
        }
    }
}
