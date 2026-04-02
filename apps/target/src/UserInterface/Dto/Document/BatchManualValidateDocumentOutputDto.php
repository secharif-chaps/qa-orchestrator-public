<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Document;

use Symfony\Component\Serializer\Annotation\Groups;

readonly class BatchManualValidateDocumentOutputDto
{
    /**
     * @param array<int, array{document_id: string, status: string, reason?: string}> $results
     */
    public function __construct(
        #[Groups(['document:batch-manual-validate:output'])]
        public bool $success,
        #[Groups(['document:batch-manual-validate:output'])]
        public int $validated_count,
        #[Groups(['document:batch-manual-validate:output'])]
        public int $failed_count,
        #[Groups(['document:batch-manual-validate:output'])]
        public string $manual_status,
        #[Groups(['document:batch-manual-validate:output'])]
        public string $validated_by,
        #[Groups(['document:batch-manual-validate:output'])]
        public \DateTimeImmutable $validated_at,
        #[Groups(['document:batch-manual-validate:output'])]
        public array $results,
    ) {
    }
}
