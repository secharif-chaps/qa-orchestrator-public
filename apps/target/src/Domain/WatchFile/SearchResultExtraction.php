<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Entity extraction from SearchResult analysis stores actors, sources, and topics.
 * Used for debugging, logging (not exposed in UI/UX).
 */
#[ORM\Entity]
class SearchResultExtraction
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private readonly ?Uuid $id;

    #[ORM\ManyToOne(targetEntity: SearchResult::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private readonly SearchResult $searchResult;

    #[ORM\Column(type: 'string', length: 20, enumType: ExtractionType::class)]
    private readonly ExtractionType $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private readonly array $data;

    #[ORM\Column(type: 'float')]
    private readonly float $confidenceScore;

    #[ORM\Column(type: 'datetime_immutable')]
    private readonly \DateTimeImmutable $extractedAt;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        SearchResult $searchResult,
        ExtractionType $type,
        array $data,
        float $confidenceScore,
        ?\DateTimeImmutable $extractedAt = null,
    ) {
        $this->searchResult = $searchResult;
        $this->type = $type;
        $this->data = $data;
        $this->confidenceScore = $confidenceScore;
        $this->extractedAt = $extractedAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        if (!isset($this->id)) {
            return null;
        }

        return $this->id->toString();
    }

    public function getSearchResult(): SearchResult
    {
        return $this->searchResult;
    }

    public function getType(): ExtractionType
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    public function getExtractedAt(): \DateTimeImmutable
    {
        return $this->extractedAt;
    }
}
