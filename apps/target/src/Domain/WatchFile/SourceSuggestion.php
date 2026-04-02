<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

readonly class SourceSuggestion
{
    public function __construct(
        public string $name,
        public string $type,
        public string $relevance,
        public int $score,
        public ?string $url = null,
    ) {
    }

    /**
     * @param array{name: string, url?: string, type: string, relevance: string, score: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            relevance: $data['relevance'],
            score: $data['score'],
            url: $data['url'] ?? null,
        );
    }

    /**
     * @return array{name: string, type: string, relevance: string, score: int, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'relevance' => $this->relevance,
            'score' => $this->score,
            'url' => $this->url,
        ];
    }
}
