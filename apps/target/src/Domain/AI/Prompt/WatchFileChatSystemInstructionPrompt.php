<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

readonly class WatchFileChatSystemInstructionPrompt
{
    use TemplatedPromptTrait;

    public function __construct(
        private PromptTemplateEngineInterface $promptTemplateEngine,
    ) {
    }

    public function getInstruction(string $conversationLanguage): string
    {
        return $this->getContent(
            'chat/watchfile/system_instruction',
            [
                'conversationLanguage' => $conversationLanguage,
            ],
        );
    }
}
