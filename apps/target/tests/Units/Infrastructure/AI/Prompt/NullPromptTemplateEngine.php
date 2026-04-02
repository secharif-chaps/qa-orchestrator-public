<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\AI\Prompt;

use App\Domain\AI\Prompt\PromptTemplateEngineInterface;

class NullPromptTemplateEngine implements PromptTemplateEngineInterface
{
    public function __invoke(string $templateName, array $parameters): string
    {
        return 'lorem ipsum';
    }

    public function getExpectedResponseSchema(string $templateName, array $parameters): ?array
    {
        return null;
    }
}
