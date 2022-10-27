<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;


class WebErrorHandler
{
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * 
     * @return ResponseInterface
     */
    public function processError(
        WebError|\Throwable $error,
        ServerRequestInterface $request,
        ResponseInterface $response
    ): ResponseInterface {
        if (!$error instanceof WebError) {
            $error = new WebError(500, $error);
        }

        return $response->withStatus($error->getCode(), $error->getMessage());
    }
}
