<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;

readonly class GoogleSubquestionsGeneratorPromptFactory
{
    use TemplatedPromptTrait;

    public function __construct(
        private PromptTemplateEngineInterface $promptTemplateEngine,
    ) {
    }

    /**
     * @return list<Prompt>
     */
    public function __invoke(WatchFile $watchFile, StrategicQuestion $strategicQuestion): array
    {
        return [
            $this->makePrompt(
                'google_subquestions_generator',
                [
                    'watchfile' => $watchFile,
                    'strategic_question' => $strategicQuestion,
                ],
            ),
        ];
    }
}
