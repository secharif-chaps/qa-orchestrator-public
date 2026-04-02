<?php

declare(strict_types=1);

namespace App\Domain\AI\Prompt;

use App\Domain\Chat\MessageRole;

readonly class Prompt
{
    public function __construct(
        public string $name,
        public string $content,
        public MessageRole $role,
        public ?array $expectedResponseFormat = null,
        public ?string $expectedResponseMimeType = null,
        public ModelType $modelType = ModelType::COMPLEX,
    ) {
    }
}
