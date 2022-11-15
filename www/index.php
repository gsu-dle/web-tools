<?php

declare(strict_types=1);

use GAState\Tools\Web\WebHandlerInterface;
use GAState\Tools\Web\WebResponseCode;
use GAState\Tools\Web\WebTemplateEngineInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

require __DIR__ . '/../vendor/autoload.php';


class ExampleWebRouter extends \GAState\Tools\Web\WebRouter
{
    /**
     * @return void
     */
    public function initRouter(): void
    {
        $webRoot = rtrim(($this->env['WEB_URI_ROOT'] ?? ''),'/');

        $map = $this->router->getMap();
        $map->attach('example', $webRoot, function($map) {
            $map->get('phpinfo', '/phpinfo', PhpInfoHandler::class);
        });
    }
}


class PhpInfoHandler implements WebHandlerInterface
{
    /**
     * @param WebTemplateEngineInterface $templateEngine
     */
    public function __construct(protected WebTemplateEngineInterface $templateEngine)
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
        if (($request->getQueryParams()['name'] ?? '') != '') {
            $response = $response
            ->withStatus(WebResponseCode::OK)
            ->withHeader('Content-Type', 'text/html');

            $response->getBody()->write($this->templateEngine->render('hello.twig', $request->getQueryParams()));

            return $response;
        }

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
}


(new \GAState\Tools\Web\WebAppFactory($_ENV['WEB_BASE_DIR'] ?? __DIR__ . '/../'))
    ->createWebApp()
    ->runWebApp();
