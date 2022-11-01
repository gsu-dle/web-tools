<?php

declare(strict_types=1);

use GAState\Tools\Web\WebResponseCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';

class MyWebRouter extends \GAState\Tools\Web\WebRouter
{
    /**
     * @return void
     */
    public function initRouter(): void
    {
        $map = $this->router->getMap();
        $map->get(
            name: 'phpinfo',
            path: ($this->env['WEB_URI_ROOT'] ?? '') . '/phpinfo',
            handler: function (
                ServerRequestInterface $request,
                ResponseInterface $response
            ) {
                ob_start();
                phpinfo();
                $phpinfo = strval(ob_get_contents());
                ob_end_clean();

                $response = $response
                    ->withStatus(WebResponseCode::OK)
                    ->withHeader('Content-Type', 'text/html');

                $response->getBody()->write($phpinfo);

                return $response;
            }
        );
    }
}


(new \GAState\Tools\Web\WebAppFactory($_ENV['WEB_BASE_DIR'] ?? __DIR__ . '/../'))
    ->createWebApp()
    ->runWebApp();
