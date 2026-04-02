<?php

namespace App\Domain\Collect\ValueObject;

use Symfony\Component\Serializer\Attribute\Groups;

readonly class ChoiceParameter extends Parameter
{
    /**
     * @param array<string, string>|null $help
     * @param list<string>               $choices
     */
    public function __construct(
        string $name,
        ?string $label,
        bool $required,
        ?array $help,
        mixed $default,
        string $type,
        #[Groups(['collector:llm'])]
        public array $choices,
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
