<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Source;

use Symfony\Component\Serializer\Annotation\Groups;

class BatchChangeSourceStatusOutputDto
{
    /**
     * @param array<int, array<string, mixed>> $results Array of individual source status change results
     * @param array<int, array<string, mixed>> $errors  Array of errors that occurred during status changes
     */
    public function __construct(
        #[Groups(['source:read'])]
        public bool $success,
        #[Groups(['source:read'])]
        public string $message,
        #[Groups(['source:read'])]
        public array $results,
        #[Groups(['source:read'])]
        public array $errors,
        #[Groups(['source:read'])]
        public int $total,
        #[Groups(['source:read'])]
        public int $processed,
        #[Groups(['source:read'])]
        public int $failed,
    ) {
    }
}
