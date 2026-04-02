<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Source;

use App\Domain\Source\CollectStatus;
use App\Domain\Source\Source;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

readonly class SourceGroup
{
    /**
     * @param Collection<int, Source> $sources
     */
    public function __construct(
        #[Groups(['source_group:read'])]
        public string $type,
        #[Groups(['source_group:read'])]
        public string $typeLabel,
        #[Groups(['source_group:read'])]
        public int $count,
        #[Groups(['source_group:read'])]
        public Collection $sources = new ArrayCollection(),
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTypeLabel(): string
    {
        return $this->typeLabel;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Counts all error sources.
     */
    public function getError(): int
    {
        return $this->sources->filter(
            fn (Source $source) => CollectStatus::ERROR === $source->getCollectStatus()
        )->count();
    }

    /**
     * Counts all active sources.
     */
    public function getRunning(): int
    {
        return $this->sources->filter(
            fn (Source $source) => CollectStatus::RUNNING === $source->getCollectStatus()
        )->count();
    }

    /**
     * Counts all disabled sources, including auto disabled and inactive sources.
     */
    public function getStopped(): int
    {
        return $this->sources->filter(
            fn (Source $source) => CollectStatus::STOPPED === $source->getCollectStatus()
        )->count();
    }

    public function getTotal(): int
    {
        return $this->count;
    }
}
