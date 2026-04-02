<?php

namespace App\Domain\Collect\ValueObject;

use Symfony\Component\Serializer\Attribute\Groups;

readonly class StringParameter extends Parameter
{
    /**
     * @param array<string, string>|null $help
     */
    public function __construct(
        string $name,
        ?string $label,
        bool $required,
        ?array $help,
        mixed $default,
        string $type,
        #[Groups(['collector:llm'])]
        public ?int $minLength,
        #[Groups(['collector:llm'])]
        public ?int $maxLength,
        #[Groups(['collector:llm'])]
        public ?string $pattern,
    ) {
        parent::__construct(
            $name,
            $label,
            $required,
            $help,
            $default,
            $type,
        );
    }
}
