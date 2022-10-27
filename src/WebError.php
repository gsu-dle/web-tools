<?php

declare(strict_types=1);

namespace GAState\Tools\Web;


class WebError extends \Exception
{
    public function __construct(
        int $statusCode = 500,
        ?\Throwable $error = null,
        string $reasonPhrase = ''
    ) {
        parent::__construct(WebResponseCode::CODES[$statusCode] ?? $reasonPhrase, $statusCode, $error);
    }
}