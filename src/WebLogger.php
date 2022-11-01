<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use DateTimeZone;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\HandlerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;

/**
 * @codeCoverageIgnore Not considered for code coverage since this is just a facade for the Logger class from 
 * Monolog\Logger
 */
class WebLogger implements LoggerInterface
{
    use LoggerTrait;

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * 
     * @return string
     */
    public static function createAccessLogEntry(
        int $receivedOn,
        ServerRequestInterface $request,
        ResponseInterface $response
    ): string {
        $receivedOn = date('[d/M/Y:H:i:s O]', $receivedOn);

        /** @var array<string,string> $serverParams */
        $serverParams = $request->getServerParams();
        $ipAddress = $serverParams['HTTP_X_FORWARDED_FOR'] ?? $serverParams['REMOTE_ADDR'] ?? '';
        $methodURI = $request->getMethod() . ' ' . $request->getUri()->getPath()
            . ' HTTP/' . $request->getProtocolVersion();

        $responseCode = $response->getStatusCode();
        $responseSize = $response->getBody()->getSize() ?? 0;

        return "{$ipAddress} - - {$receivedOn} \"{$methodURI}\" {$responseCode} {$responseSize}";
    }


    /**
     * @var string $requestId
     */
    public string $requestId;


    /**
     * @var Logger $logger
     */
    protected Logger $logger;


    /**
     * @param string $filePath
     * @param string $name
     * @param string $logLevel
     * @param string|null $format
     * @param DateTimeZone|null $timezone
     */
    public function __construct(
        string $filePath,
        string $name = 'WebApp',
        string $logLevel = 'warning',
        ?string $format = null,
        ?DateTimeZone $timezone = null
    ) {
        $this->requestId = uniqid('', true);
        $logHandler = new StreamHandler(
            stream: $filePath,
            level: match ($logLevel) {
                'debug', 'Debug', 'DEBUG' => Level::Debug,
                'info', 'Info', 'INFO' => Level::Info,
                'notice', 'Notice', 'NOTICE' => Level::Notice,
                'warning', 'Warning', 'WARNING' => Level::Warning,
                'error', 'Error', 'ERROR' => Level::Error,
                'critical', 'Critical', 'CRITICAL' => Level::Critical,
                'alert', 'Alert', 'ALERT' => Level::Alert,
                'emergency', 'Emergency', 'EMERGENCY' => Level::Emergency,
                default => Level::Warning
            }
        );
        $logHandler->setFormatter(
            new LineFormatter($format ?? "[%datetime%][%channel%][%level_name%][{$this->requestId}]: %message% - %context%\n")
        );

        $this->logger = new Logger(
            name: $name,
            timezone: $timezone,
            handlers: [$logHandler]
        );
    }


    /**
     * @param mixed  $level
     * @param string|\Stringable $message
     * @param array<mixed> $context
     *
     * @return void
     *
     * @throws \Psr\Log\InvalidArgumentException
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        /** @var Level|LogLevel::* $level */
        $this->logger->log($level, $message, $context);
    }
}
