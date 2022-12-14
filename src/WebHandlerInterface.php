<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

interface WebHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * 
     * @return void
     */
    public function process(
        ServerRequestInterface &$request,
        ResponseInterface &$response
    ): void;
}
