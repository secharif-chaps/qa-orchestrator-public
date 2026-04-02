<?php

declare(strict_types=1);

namespace App\Domain\Source;

use App\Domain\Shared\TranslatedText;
use Symfony\Component\Serializer\Attribute\Groups;

readonly class SourceTypeItem
{
    public function __construct(
        #[Groups('source_type:read')]
        public string $type,
        #[Groups('source_type:read')]
        public TranslatedText $name,
        #[Groups('source_type:read')]
        public TranslatedText $description,
        #[Groups('source_type:read')]
        public ?string $icon = null,
    ) {
    }
}
