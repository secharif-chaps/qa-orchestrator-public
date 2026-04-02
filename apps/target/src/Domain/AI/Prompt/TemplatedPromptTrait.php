<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

use App\Domain\Chat\MessageRole;

/**
 * @property PromptTemplateEngineInterface $promptTemplateEngine
 */
trait TemplatedPromptTrait
{
    public function getContent(string $name, array $parameters): string
    {
        return ($this->promptTemplateEngine)($name, $parameters);
    }

    public function getExpectedResponseFormat(string $name, array $parameters): ?array
    {
        return $this->promptTemplateEngine->getExpectedResponseSchema($name, $parameters);
    }

    public function makePrompt(string $name, array $parameters, MessageRole $role = MessageRole::User): Prompt
    {
        $expectedResponseMimeType = null;
        $expectedResponseFormat = $this->getExpectedResponseFormat($name, $parameters);
        if (null !== $expectedResponseFormat) {
            $expectedResponseMimeType = 'application/json';
        }

        return new Prompt(
            $name,
            $this->getContent($name, $parameters),
            $role,
            $expectedResponseFormat,
            $expectedResponseMimeType,
        );
    }
}
