<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

use App\Domain\WatchFile\WatchFile;

readonly class DetectMonitoringTypePromptFactory
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
        return [
            $this->makePrompt('detect_monitoring_type', [
                'watchfile' => $watchFile,
            ],),
        ];
    }
}
