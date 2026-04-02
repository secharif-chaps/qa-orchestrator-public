<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

readonly class ActorSuggestion
{
    public function __construct(
        public string $name,
        public string $type,
        public string $relevance,
        public int $score,
    ) {
    }

    /**
     * @param array{name: string, type: string, relevance: string, score: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            type: $data['type'],
            relevance: $data['relevance'],
            score: $data['score'],
        );
    }

    /**
     * @return array{name: string, type: string, relevance: string, score: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type,
            'relevance' => $this->relevance,
            'score' => $this->score,
        ];
    }
}
