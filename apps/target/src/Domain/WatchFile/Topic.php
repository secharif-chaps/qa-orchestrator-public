<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

readonly class Topic
{
    /**
     * @param list<string> $keywords
     */
    public function __construct(
        public string $label,
        public array $keywords,
        public int $relevanceScore,
        public string $searchQueryTemplate,
    ) {
    }

    /**
     * @param array{label: string, keywords: list<string>, relevanceScore: int, searchQueryTemplate: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            label: $data['label'],
            keywords: $data['keywords'],
            relevanceScore: $data['relevanceScore'],
            searchQueryTemplate: $data['searchQueryTemplate'],
        );
    }

    /**
     * @return array{label: string, keywords: list<string>, relevanceScore: int, searchQueryTemplate: string}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'keywords' => $this->keywords,
            'relevanceScore' => $this->relevanceScore,
            'searchQueryTemplate' => $this->searchQueryTemplate,
        ];
    }
}
