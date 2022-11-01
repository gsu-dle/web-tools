<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class ClosureWebHandler implements WebHandlerInterface
{
    public function __construct(protected LoggerInterface $logger, protected \Closure $handler)
    {
    }


    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * 
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        $response = $this->handler->call($this, $request, $response);
        if (!$response instanceof ResponseInterface) {
            throw new WebError(WebResponseCode::INTERNAL_SERVER_ERROR);
        }
        return $response;
    }
}
