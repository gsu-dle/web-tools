<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class WebApp implements WebAppInterface
{
    /**
     * @var int $started
     */
    protected int $started;


    /**
     * @param array<string,string> $env
     * @param LoggerInterface $logger
     * @param ServerRequestCreatorInterface $requestFactory
     * @param ResponseFactoryInterface $responseFactory
     * @param WebRouterInterface $router
     * @param EmitterInterface $responseEmitter
     */
    public function __construct(
        protected array $env,
        protected LoggerInterface $logger,
        protected ServerRequestCreatorInterface $requestFactory,
        protected ResponseFactoryInterface $responseFactory,
        protected WebRouterInterface $router,
        protected EmitterInterface $responseEmitter
    ) {
        $this->started = time();
        $this->router->initRouter();
    }


    public function runWebApp(): void
    {
        $request = $response = null;

        try {
            // Turn off standard PHP output
            ob_start();

            // Get everything prepped to process the request
            $request = $this->requestFactory->fromGlobals();
            $response = $this->responseFactory->createResponse();

            // Process the request
            try {
                $this->preProcessRequest($request);

                $webHandler = $this->router->getWebHandler($request);

                $this->processRequest($webHandler, $request, $response);

                $this->postProcessRequest($request, $response);
            } catch (Throwable $error) {
                $request = $request->withAttribute('error', $error);
                $webHandler = $this->router->getWebErrorHandler($request, $error);
                $this->processRequest($webHandler, $request, $response);

                $this->logger->error('An error has occurred', [$error->__toString()]);
                $this->logger->error(WebLogger::createAccessLogEntry($this->started, $request, $response));
            }

            // Clean up anything that may have been written out and put a warning in the log about it
            $contents = ob_get_clean();
            if ($contents !== false && strlen($contents) > 0) {
                $this->logger->info('Output captured outside of response');
            }

            $headers = headers_list();
            if (count($headers) > 0) {
                $this->logger->info('Headers captured outside of response', $headers);
            }
            header_remove();

            // Emit the response
            $this->emitResponse($response);

            $this->logger->info(WebLogger::createAccessLogEntry($this->started, $request, $response));
            $this->logger->debug("DEBUG_INFO", [
                'SERVER_PARAMS' => $request->getServerParams(),
                'REQUEST_HEADERS' => $request->getHeaders(),
                'REQUEST_SIZE' => $request->getBody()->getSize() ?? 0,
                'RESPONSE_HEADERS' => $response->getHeaders(),
                'RESPONSE_SIZE' => $response->getBody()->getSize() ?? 0
            ]);
        } catch (Throwable $fatal) {
            $this->logger->emergency('A fatal error has occurred; Aborting execution', [$fatal->__toString()]);
            if ($request !== null && $response !== null) {
                $this->logger->emergency(WebLogger::createAccessLogEntry($this->started, $request, $response));
            }

            http_response_code(500);
            die(1);
        }
    }


    /**
     * @param ServerRequestInterface $request
     * 
     * @return void
     */
    protected function preProcessRequest(ServerRequestInterface &$request): void
    {
    }


    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * 
     * @return void
     */
    protected function postProcessRequest(
        ServerRequestInterface &$request,
        ResponseInterface &$response
    ): void {
    }


    /**
     * @param WebHandlerInterface $webHandler
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * 
     * @return void
     */
    protected function processRequest(
        WebHandlerInterface $webHandler,
        ServerRequestInterface &$request,
        ResponseInterface &$response
    ): void {
        $webHandler->process($request, $response);
    }


    /**
     * @param ResponseInterface $response
     * 
     * @return void
     */
    protected function emitResponse(ResponseInterface $response): void
    {
        $this->responseEmitter->emit($response);
    }
}
