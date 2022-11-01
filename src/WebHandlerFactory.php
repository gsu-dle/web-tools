<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Closure;
use Psr\Log\LoggerInterface;

class WebHandlerFactory
{
    /**
     * @param LoggerInterface $logger
     * @param Closure $factory
     */
    public function __construct(
        protected LoggerInterface $logger,
        protected Closure $factory
    ) {
    }


    /**
     * @param Closure|string $webHandler
     * 
     * @return WebHandlerInterface
     */
    public function createWebHandler(Closure|string $webHandler): WebHandlerInterface
    {
        $factory = $this->factory;
        $inst = $factory($webHandler);
        if (!$inst instanceof WebHandlerInterface) {
            throw new WebError(WebResponseCode::INTERNAL_SERVER_ERROR);
        }

        return $inst;
    }
}
