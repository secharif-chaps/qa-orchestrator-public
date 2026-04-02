<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Represents the sources associated with a specific timeline event.
 */
final readonly class EventSources
{
    /**
     * @param array<string, mixed>             $event
     * @param array<\App\Domain\Source\Source> $sources
     */
    public function __construct(
        #[Groups(['source:read'])]
        public array $event,

        #[Groups(['source:read'])]
        public array $sources,

        #[Groups(['source:read'])]
        public int $count,
    ) {
    }
}
