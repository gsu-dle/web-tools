<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

(new \GAState\Tools\Web\WebApp($_ENV['WEB_BASE_DIR'] ?? __DIR__ . '/../'))->run();
