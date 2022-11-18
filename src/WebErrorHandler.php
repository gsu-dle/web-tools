<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class WebErrorHandler implements WebHandlerInterface
{
    public function __construct(protected LoggerInterface $logger)
    {
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
        $error = $request->getAttribute('error');
        if (!$error instanceof Throwable) {
            $error = new WebError(WebResponseCode::INTERNAL_SERVER_ERROR);
        }
        if (!$error instanceof WebError) {
            $error = new WebError(500, $error);
        }
        $response = $response->withStatus($error->getCode(), $error->getMessage());
    }
}
