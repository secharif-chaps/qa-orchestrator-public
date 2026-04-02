<?php

declare(strict_types=1);

namespace App\Application\WatchFile\Source;

use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;

readonly class AddSourceAction
{
    public function __construct(
        public string $watchFileId,
        public string $name,
        public string $type,
        public string $primaryDomain,
        public string $url,
        public ?string $query,
        public TranslatedText $description,
        public TranslatedText $relevance,
        /**
         * @var array<string, mixed>|null
         */
        public ?array $parameters = null,
        public ?string $messageId = null,
        public ?string $actorId = null,
    ) {
    }

    public function toEntity(): Source
    {
        $type = SourceType::from($this->type);

        return new Source(
            name: $this->name,
            description: $this->description,
            type: $type,
            url: $this->url,
            primaryDomain: $this->primaryDomain,
            relevance: $this->relevance,
            actor: null, // Actor will be set by the handler using actorId
            query: $this->query,
            parameters: $this->parameters,
        );
    }
}
