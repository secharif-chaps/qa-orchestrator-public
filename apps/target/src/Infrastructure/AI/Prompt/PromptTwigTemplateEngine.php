<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Prompt;

use App\Domain\AI\Prompt\PromptTemplateEngineInterface;
use Twig\Environment;
use Twig\Error\LoaderError;

readonly class PromptTwigTemplateEngine implements PromptTemplateEngineInterface
{
    public function __construct(
        private Environment $environment,
    ) {
    }

    private function getTemplatePath(string $templateName, string $extension): string
    {
        return \sprintf('prompts/%s.%s', $templateName, $extension);
    }

    public function __invoke(string $templateName, array $parameters): string
    {
        $templateName = $this->getTemplatePath($templateName, 'md.twig');
        $template = $this->environment->load($templateName);

        return $template->render($parameters);
    }

    public function getExpectedResponseSchema(string $templateName, array $parameters): ?array
    {
        try {
            $templateName = $this->getTemplatePath($templateName, 'json.twig');
            $template = $this->environment->load($templateName);
        } catch (LoaderError) {
            return null;
        }

        $string = $template->render($parameters);

        try {
            return json_decode($string, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
