<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

readonly class DetectLanguagePrompt
{
    use TemplatedPromptTrait;

    public function __construct(
        private PromptTemplateEngineInterface $promptTemplateEngine,
    ) {
    }

    /**
     * @return list<Prompt>
     */
    public function __invoke(string $text): array
    {
        return [
            $this->makePrompt('detect_language', [
                'text' => $text,
            ]),
        ];
    }
}
