<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

interface PromptTemplateEngineInterface
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __invoke(string $templateName, array $parameters): string;

    /**
     * @param array<string, mixed> $parameters
     */
    public function getExpectedResponseSchema(string $templateName, array $parameters): ?array;
}
