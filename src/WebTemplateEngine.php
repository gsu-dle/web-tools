<?php

declare(strict_types=1);

namespace GAState\Tools\Web;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Loader\LoaderInterface;

class WebTemplateEngine implements WebTemplateEngineInterface
{
    /**
     * @var LoaderInterface $loader
     */
    protected LoaderInterface $loader;


    /**
     * @var Environment $twig
     */
    protected Environment $twig;


    public function __construct(string $templateDir)
    {
        $this->loader = new FilesystemLoader($templateDir);
        $this->twig = new Environment($this->loader);
    }


    /**
     * @param string $template
     * @param array<string,mixed> $context
     * 
     * @return string
     */
    public function render(
        string $template,
        array $context
    ): string {
        return $this->twig->render($template, $context);
    }
}
