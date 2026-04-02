<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Actor;

use Symfony\Component\Serializer\Annotation\Groups;

class BatchChangeActorStatusOutputDto
{
    /**
     * @param array<int, array<string, mixed>> $results Array of individual actor status change results
     * @param array<int, array<string, mixed>> $errors  Array of errors that occurred during status changes
     */
    public function __construct(
        #[Groups(['actor:read'])]
        public bool $success,
        #[Groups(['actor:read'])]
        public string $message,
        #[Groups(['actor:read'])]
        public array $results,
        #[Groups(['actor:read'])]
        public array $errors,
        #[Groups(['actor:read'])]
        public int $total,
        #[Groups(['actor:read'])]
        public int $processed,
        #[Groups(['actor:read'])]
        public int $failed,
    ) {
    }
}
