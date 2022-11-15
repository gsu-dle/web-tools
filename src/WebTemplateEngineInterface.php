<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

interface WebTemplateEngineInterface
{
    /**
     * @param string $template
     * @param array<string,mixed> $context
     * 
     * @return string
     */
    public function render(
        string $template,
        array $context
    ): string;
}
