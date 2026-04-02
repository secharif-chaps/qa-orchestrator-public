<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Document;

use Symfony\Component\Serializer\Annotation\Groups;

readonly class ManualValidateDocumentOutputDto
{
    public function __construct(
        #[Groups(['document:manual-validate:output'])]
        public bool $success,
        #[Groups(['document:manual-validate:output'])]
        public string $document_id,
        #[Groups(['document:manual-validate:output'])]
        public ?string $manual_status,
        #[Groups(['document:manual-validate:output'])]
        public ?string $validated_by,
        #[Groups(['document:manual-validate:output'])]
        public ?\DateTimeImmutable $validated_at,
    ) {
    }
}
