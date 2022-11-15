<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Aura\Router\Map;
use Aura\Router\Route;
use Aura\Router\RouterContainer;
use Auryn\Injector;
use Closure;
use Dotenv\Dotenv;
use Exception;
use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7Server\ServerRequestCreatorInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class WebAppFactory
{
    /**
     * @var string|null $envPath
     */
    protected ?string $envPath;


    /**
     * @var array<string, string> $env
     */
    protected array $env;


    /**
     * @var Injector $injector
     */
    protected Injector $injector;


    /**
     * @param string|null $envPath
     * 
     * @return void
     */
    public function __construct(?string $envPath = null)
    {
        $this->envPath = $envPath;
        $this->env = [];
        $this->injector = new Injector();

        $this->loadEnv();
        $this->declareDependencies();
    }


    /**
     * @return void
     */
    protected function loadEnv(): void
    {
        global $_ENV;

        if (!isset($_ENV['WEB_BASE_DIR'])) {
            $_ENV['WEB_BASE_DIR'] = strval(realpath($this->envPath ?? __DIR__));
        }

        if ($this->envPath !== null) {
            Dotenv::createImmutable($this->envPath)->load();
        }

        foreach ($_ENV as $name => $value) {
            $this->env[strval($name)] = strval($value);
        }
    }


    /**
     * @return void
     */
    protected function declareDependencies(): void
    {
        $this->declareEnvironment();
        $this->declareLogger();
        $this->declareRequestFactory();
        $this->declareResponseFactory();
        $this->declareRouterContainer();
        $this->declareWebHandlerFactory();
        $this->declareWebRouter();
        $this->declareResponseEmitter();
        $this->declareWebTemplateEngine();
        $this->declareWebApp();
    }


    /**
     * @return void
     */
    protected function declareEnvironment(): void
    {
        $this->injector->defineParam('env', $this->env);
    }


    /**
     * @return void
     */
    protected function declareLogger(): void
    {
        if (isset($this->env['WEB_LOG_FILE'])) {
            $this->injector
                ->define(WebLogger::class, [
                    ':filePath' => $this->env['WEB_LOG_FILE'],
                    ':name' => $this->env['WEB_LOG_NAME'] ?? 'WebApp',
                    ':logLevel' => $this->env['WEB_LOG_LEVEL'] ?? 'warning',
                    ':format' => $this->env['WEB_LOG_FORMAT'] ?? null,
                    ':timezone' => null
                ])
                ->share(WebLogger::class)
                ->alias(LoggerInterface::class, WebLogger::class);
        } else {
            $this->injector
                ->share(NullLogger::class)
                ->alias(LoggerInterface::class, NullLogger::class);
        }
    }


    /**
     * @return void
     */
    protected function declareRequestFactory(): void
    {
        $this->injector
            ->alias(RequestFactoryInterface::class, Psr17Factory::class)
            ->alias(ServerRequestFactoryInterface::class, Psr17Factory::class)
            ->alias(ResponseFactoryInterface::class, Psr17Factory::class)
            ->alias(StreamFactoryInterface::class, Psr17Factory::class)
            ->alias(UploadedFileFactoryInterface::class, Psr17Factory::class)
            ->alias(UriFactoryInterface::class, Psr17Factory::class)
            ->alias(ServerRequestCreatorInterface::class, ServerRequestCreator::class);
    }


    /**
     * @return void
     */
    protected function declareResponseFactory(): void
    {
        $this->injector->alias(ResponseFactoryInterface::class, Psr17Factory::class);
    }


    /**
     * @return void
     */
    protected function declareRouterContainer(): void
    {
        $prepRouterContainer = function(RouterContainer $inst, Injector $injector) {
            $inst->setMapFactory(function () use ($injector) {
                /** @var Map $mapper */
                $mapper = $injector->make(Map::class);
                return $mapper;
            });

            $inst->setRouteFactory(function () use ($injector) {
                /** @var Route $route */
                $route = $injector->make(Route::class);
                return $route;
            });
        };

        $this->injector->prepare(RouterContainer::class, $prepRouterContainer);
    }


    /**
     * @return void
     */
    protected function declareWebRouter(): void
    {
        $webRouterClass = $this->env['WEB_ROUTER'] ?? '';
        if ($webRouterClass === '') {
            throw new Exception('WEB_ROUTER is not defined');
        }
        if (!is_subclass_of($webRouterClass, WebRouterInterface::class)) {
            throw new Exception("WEB_ROUTER is not of type WebRouterInterface: {$webRouterClass}");
        }

        $this->injector->alias(WebRouterInterface::class, $webRouterClass);
    }


    /**
     * @return void
     */
    protected function declareWebHandlerFactory(): void
    {
        /** @var Closure $webHandlerFactory */
        $webHandlerFactory = (function(Closure|string $webHandler) {
            $inst = null;

            if ($webHandler instanceof Closure) {
                $inst = $this->injector->make(ClosureWebHandler::class, [
                    ':handler' => $webHandler
                ]);
            } elseif (class_exists($webHandler)) {
                $inst = $this->injector->make($webHandler);
            }

            if (!$inst instanceof WebHandlerInterface) {
                throw new WebError(WebResponseCode::INTERNAL_SERVER_ERROR);
            }

            return $inst;
        })->bindTo($this, $this);

        $this->injector
            ->define(WebHandlerFactory::class, [
                ':factory' => $webHandlerFactory
            ])
            ->share(WebHandlerFactory::class);
    }


    /**
     * @return void
     */
    protected function declareResponseEmitter(): void
    {
        $this->injector->alias(EmitterInterface::class, SapiEmitter::class);
    }


    /**
     * @return void
     */
    protected function declareWebTemplateEngine(): void
    {
        $this->injector
            ->define(WebTemplateEngine::class, [
                ':templateDir' => $this->env['WEB_TEMPLATE_DIR'] ?? ''
            ])
            ->share(WebTemplateEngine::class)
            ->alias(WebTemplateEngineInterface::class, WebTemplateEngine::class);
    }


    /**
     * @return void
     */
    protected function declareWebApp(): void
    {
        $this->injector->alias(WebAppInterface::class, WebApp::class);
    }


    /**
     * @return WebAppInterface
     */
    public function createWebApp(): WebAppInterface
    {
        /** @var WebAppInterface $webApp */
        $webApp = $this->injector->make(WebAppInterface::class);
        return $webApp;
    }
}
