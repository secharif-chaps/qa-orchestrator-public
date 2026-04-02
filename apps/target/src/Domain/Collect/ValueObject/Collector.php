<?php

declare(strict_types=1);

namespace App\Domain\Collect\ValueObject;

use App\Domain\Source\SourceType;
use Symfony\Component\Serializer\Attribute\Groups;

readonly class Collector
{
    public function __construct(
        #[Groups(['collector:llm'])]
        public string $name,
        /**
         * @var array<string, string>
         */
        #[Groups(['collector:llm'])]
        public array $displayName,
        /**
         * @var array<string, string>
         */
        #[Groups(['collector:llm'])]
        public array $description,
        #[Groups(['collector:llm'])]
        public string $type,
        #[Groups(['collector:llm'])]
        public string $version,
        public string $iconUrl,
        /**
         * @var list<Parameter>
         */
        #[Groups(['collector:llm'])]
        public array $parameters,
        /**
         * @var list<string>
         */
        #[Groups(['collector:llm'])]
        public array $returnTypes,
        #[Groups(['collector:llm'])]
        public bool $supportStream,
        #[Groups(['collector:llm'])]
        public bool $supportBatch,
        /**
         * @var list<SourceType>$supportedSourceTypes
         */
        #[Groups(['collector:llm'])]
        public array $supportedSourceTypes,
    ) {
    }

    public function isSupportedSourceType(SourceType $sourceType): bool
    {
        return \in_array($sourceType, $this->supportedSourceTypes, true);
    }
}
