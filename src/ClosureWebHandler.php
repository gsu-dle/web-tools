<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class ClosureWebHandler implements WebHandlerInterface
{
    /**
     * @param LoggerInterface $logger
     * @param Closure $handler
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected Closure $handler
    ) {
    }


    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * 
     * @return void
     */
    public function process(
        ServerRequestInterface &$request,
        ResponseInterface &$response
    ): void {
        $response = $this->handler->call($this, $request, $response);
        if (!$response instanceof ResponseInterface) {
            throw new WebError(WebResponseCode::INTERNAL_SERVER_ERROR);
        }
    }
}
