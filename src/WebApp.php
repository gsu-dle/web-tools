<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Auryn\Injector;
use Dotenv\Dotenv;
use Monolog\Formatter\LineFormatter;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;


class WebApp
{
    /**
     * @var int $started
     */
    protected int $started;


    /**
     * @var array<string, string> $env
     */
    protected array $env;


    /**
     * @var Injector $injector
     */
    protected Injector $injector;


    /**
     * @var LoggerInterface $logger
     */
    protected LoggerInterface $logger;


    /**
     * @var RouterContainer $router
     */
    protected RouterContainer $router;


    /**
     * @var WebErrorHandler $defaultErrorHandler
     */
    protected WebErrorHandler $defaultErrorHandler;


    #region init_method


    /**
     * @param string|null $envPath
     */
    public function __construct(?string $envPath = null)
    {
        $this->started = time();

        $this->env = $this->loadEnv($envPath);
        $this->injector = $this->loadInjector();
        $this->logger = $this->loadLogger();
        $this->router = $this->loadRouter();
        $this->defaultErrorHandler = $this->loadDefaultErrorHandler();

        $this->logger->debug(static::class . '::__construct');
    }


    public function __destruct()
    {
        $this->logger->debug(static::class . '::__destruct');
    }


    /**
     * @param string|null $envPath
     * 
     * @return array<string, string>
     */
    protected function loadEnv(?string $envPath): array
    {
        global $_ENV;

        if ($envPath !== null) {
            if (!isset($_ENV['WEB_BASE_DIR'])) {
                $_ENV['WEB_BASE_DIR'] = strval(realpath($envPath));
            }
            Dotenv::createImmutable($envPath)->load();
        }

        $env = [];
        foreach ($_ENV as $name => $value) {
            $env[strval($name)] = strval($value);
        }

        return $env;
    }


    /**
     * @return Injector
     */
    protected function loadInjector(): Injector
    {
        $injector = new Injector();

        $this->defineLogger($injector);
        $this->defineHttpFactories($injector);
        $this->defineDefaultErrorHandler($injector);

        return $injector;
    }


    /**
     * @param Injector $injector
     * 
     * @return void
     */
    protected function defineLogger(Injector $injector): void
    {
        if (isset($this->env['WEB_LOG_FILE'])) {
            $requestId = uniqid('',true);
            $logHandler = new \Monolog\Handler\StreamHandler(
                stream: $this->env['WEB_LOG_FILE'],
                level: match ($this->env['WEB_LOG_LEVEL'] ?? 'warning') {
                    'debug', 'Debug', 'DEBUG' => \Monolog\Level::Debug,
                    'info', 'Info', 'INFO' => \Monolog\Level::Info,
                    'notice', 'Notice', 'NOTICE' => \Monolog\Level::Notice,
                    'warning', 'Warning', 'WARNING' => \Monolog\Level::Warning,
                    'error', 'Error', 'ERROR' => \Monolog\Level::Error,
                    'critical', 'Critical', 'CRITICAL' => \Monolog\Level::Critical,
                    'alert', 'Alert', 'ALERT' => \Monolog\Level::Alert,
                    'emergency', 'Emergency', 'EMERGENCY' => \Monolog\Level::Emergency,
                    default => \Monolog\Level::Warning
                }
            );
            $logHandler->setFormatter(new LineFormatter("[%datetime%][%channel%][%level_name%][{$requestId}]: %message% - %context%\n"));

            $injector
                ->define(\Monolog\Logger::class, [
                    ':name' => $this->env['WEB_LOG_NAME'] ?? static::class,
                    ':handlers' => [$logHandler],
                    ':processors' => [],
                    ':timezone' => null
                ])
                ->share(\Monolog\Logger::class)
                ->alias(LoggerInterface::class, \Monolog\Logger::class);
        } else {
            $injector
                ->share(\Psr\Log\NullLogger::class)
                ->alias(LoggerInterface::class, \Psr\Log\NullLogger::class);
        }
    }


    /**
     * @param Injector $injector
     * 
     * @return void
     */
    protected function defineHttpFactories(Injector $injector): void
    {
        $injector
            ->share(\Nyholm\Psr7\Factory\Psr17Factory::class)
            ->alias(RequestFactoryInterface::class, \Nyholm\Psr7\Factory\Psr17Factory::class)
            ->alias(ServerRequestFactoryInterface::class, \Nyholm\Psr7\Factory\Psr17Factory::class)
            ->alias(ResponseFactoryInterface::class, \Nyholm\Psr7\Factory\Psr17Factory::class)
            ->alias(StreamFactoryInterface::class, \Nyholm\Psr7\Factory\Psr17Factory::class)
            ->alias(UploadedFileFactoryInterface::class, \Nyholm\Psr7\Factory\Psr17Factory::class)
            ->alias(UriFactoryInterface::class, \Nyholm\Psr7\Factory\Psr17Factory::class);
    }


    /**
     * @param Injector $injector
     * 
     * @return void
     */
    protected function defineDefaultErrorHandler(Injector $injector): void
    {
        $injector
            ->alias(WebErrorHandler::class, WebErrorHandler::class);
    }


    /**
     * @return LoggerInterface
     */
    protected function loadLogger(): LoggerInterface
    {
        /** @var LoggerInterface */
        return $this->injector->make(LoggerInterface::class);
    }


    /**
     * @return RouterContainer
     */
    protected function loadRouter(): RouterContainer
    {
        /** @var RouterContainer $router */
        $router = $this->injector->make(RouterContainer::class);
        $this->defineRoutes($router, $router->getMap());
        return $router;
    }


    /**
     * @param RouterContainer $router
     * @param Map $map
     * 
     * @return void
     */
    protected function defineRoutes(
        RouterContainer $router,
        Map $map
    ): void {
        $map
            ->get(
                name: 'phpinfo',
                path: ($this->env['WEB_URI_ROOT'] ?? '') . '/phpinfo',
                handler: function (ServerRequestInterface $request, ResponseInterface $response) {
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


    /**
     * @return WebErrorHandler
     */
    protected function loadDefaultErrorHandler(): WebErrorHandler
    {
        /** @var WebErrorHandler */
        return $this->injector->make(WebErrorHandler::class);
    }


    #endregion init_methods


    #region execute_methods


    /**
     * @return void
     */
    public function run(): void
    {
        $this->logger->debug(static::class . "::run");

        // Turn off any output
        ob_start();

        // Build our request
        $request = $this->getRequest();

        // Let's find a handler for the request and process it the generate a response
        try {
            $webHandler = $this->getWebHandler($request);

            $this->logger->debug($webHandler::class . ':process');
            $response = $webHandler->process($request, $this->getResponse());
        } catch (WebError | \Throwable $error) {
            $actualError = ($error instanceof WebError) ? $error->getPrevious() : $error;

            $this->logger->debug($this->defaultErrorHandler::class . '::processError', $actualError !== null ? [$actualError] : []);
            $response = $this->defaultErrorHandler->processError($error, $request, $this->getResponse());

            $this->logger->error($this->createAccessLogEntry($request, $response), $actualError !== null ? [$actualError] : []);
        }

        // Clean up everything before emitting the response
        ob_end_clean();
        header_remove();

        // Emit the response
        $this->emitResponse($response);

        $this->logger->info($this->createAccessLogEntry($request, $response));
        $this->logger->debug("DEBUG_INFO", [
            'SERVER_PARAMS' => $request->getServerParams(),
            'REQUEST_HEADERS' => $request->getHeaders(),
            'REQUEST_SIZE' => $request->getBody()->getSize() ?? 0,
            'RESPONSE_HEADERS' => $response->getHeaders(),
            'RESPONSE_SIZE' => $response->getBody()->getSize()
        ]);
    }


    /**
     * @return ServerRequestInterface
     */
    protected function getRequest(): ServerRequestInterface
    {
        $this->logger->debug(static::class . '::getRequest');

        /** @var \Nyholm\Psr7Server\ServerRequestCreator $serverRequestFactory */
        $serverRequestFactory = $this->injector->make(\Nyholm\Psr7Server\ServerRequestCreator::class);
        return $serverRequestFactory->fromGlobals();
    }


    /**
     * @return ResponseInterface
     */
    protected function getResponse(): ResponseInterface
    {
        $this->logger->debug(static::class . '::getResponse');

        /** @var ResponseFactoryInterface $responseFactory */
        $responseFactory = $this->injector->make(ResponseFactoryInterface::class);
        return $responseFactory
            ->createResponse()
            ->withStatus(WebResponseCode::OK)
            ->withHeader('Content-Type', $this->env['WEB_CONTENT_TYPE'] ?? 'text/html');
    }


    /**
     * @param ServerRequestInterface $request
     * 
     * @return WebHandler
     */
    protected function getWebHandler(ServerRequestInterface $request): WebHandler
    {
        // Match the request to a route
        $matcher = $this->router->getMatcher();
        $route = $matcher->match($request);
        if (!$route instanceof Route) {
            switch ($matcher->getFailedRoute()?->failedRule) {
                case 'Aura\Router\Rule\Allows':
                    throw new WebError(WebResponseCode::METHOD_NOT_ALLOWED);
                case 'Aura\Router\Rule\Accepts':
                    throw new WebError(WebResponseCode::NOT_ACCEPTABLE);
                default:
                    throw new WebError(WebResponseCode::NOT_FOUND);
            }
        }

        // Add any route attributes to the request
        foreach ($route->attributes as $key => $val) {
            $request = $request->withAttribute($key, $val);
        }

        // Create a handler for the request
        $webHandler = null;

        if ($route->handler instanceof \Closure) {
            $webHandler = $this->injector->make(ClosureWebHandler::class, [
                ':handler' => $route->handler
            ]);
        } elseif (is_string($route->handler)) {
            $webHandler = $this->injector->make($route->handler);
        }

        if (!$webHandler instanceof WebHandler) {
            $this->logger->error('Mapper returned an invalid WebHandler');
            throw new WebError(WebResponseCode::INTERNAL_SERVER_ERROR);
        }

        return $webHandler;
    }


    /**
     * @param ResponseInterface $response
     * 
     * @return void
     */
    protected function emitResponse(ResponseInterface $response): void
    {
        $this->logger->debug(static::class . '::emitResponse');

        (new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
    }


    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * 
     * @return string
     */
    protected function createAccessLogEntry(ServerRequestInterface $request, ResponseInterface $response): string
    {
        $receivedOn = date('[d/M/Y:H:i:s O]', $this->started);
        $ipAddress = $request->getServerParams()['HTTP_X_FORWARDED_FOR'] ?? $request->getServerParams()['REMOTE_ADDR'] ?? '';
        $methodURI = $request->getMethod() . ' ' . $request->getUri()->getPath() . ' HTTP/' . $request->getProtocolVersion();
        $responseCode = $response->getStatusCode();
        $responseSize = $response->getBody()->getSize() ?? 0;

        return "{$ipAddress} - - {$receivedOn} \"{$methodURI}\" {$responseCode} {$responseSize}";
    }

    #endregion execute_methods
}
