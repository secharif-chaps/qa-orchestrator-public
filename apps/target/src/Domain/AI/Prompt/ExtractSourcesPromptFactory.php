<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;

readonly class ExtractSourcesPromptFactory
{
    use TemplatedPromptTrait;

    public function __construct(
        private PromptTemplateEngineInterface $promptTemplateEngine,
    ) {
    }

    /**
     * @return list<Prompt>
     */
    public function __invoke(WatchFile $watchFile): array
    {
        $sourceTypes = [];
        foreach (SourceType::cases() as $sourceType) {
            $sourceTypes[] = $sourceType->value;
        }

        return [
            $this->makePrompt('extract_sources', [
                'watchfile' => $watchFile,
                'sourceTypes' => $sourceTypes,
            ],),
        ];
    }
}
