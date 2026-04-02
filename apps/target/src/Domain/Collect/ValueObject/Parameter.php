<?php

namespace App\Domain\Collect\ValueObject;

use Symfony\Component\Serializer\Attribute\Groups;

readonly class Parameter
{
    public function __construct(
        #[Groups(['collector:llm'])]
        public string $name,
        #[Groups(['collector:llm'])]
        public ?string $label,
        #[Groups(['collector:llm'])]
        public bool $required,
        /**
         * @var array<string, string>|null
         */
        #[Groups(['collector:llm'])]
        public ?array $help,
        #[Groups(['collector:llm'])]
        public mixed $default,
        #[Groups(['collector:llm'])]
        public string $type,
    ) {
    }
}
