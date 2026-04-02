<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Webmozart\Assert\Assert;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['search_term_hash', 'country', 'language', 'strategic_question_id'])]
#[ORM\HasLifecycleCallbacks]
class SearchQuery
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watch_file:llm'])]
    private ?string $id = null;

    #[ORM\Column(type: 'text', length: 255)]
    #[Groups(['watch_file:llm'])]
    private string $searchTerm;

    #[ORM\Column(type: 'string', length: 32)]
    private string $searchTermHash;

    #[ORM\Column(type: 'string', length: 2)]
    #[Groups(['watch_file:llm'])]
    private string $country;

    #[ORM\Column(type: 'string', length: 2)]
    #[Groups(['watch_file:llm'])]
    private string $language;

    #[ORM\Column(type: 'string', length: 50)]
    #[Groups(['watch_file:llm'])]
    private string $queryType;

    #[ORM\Column(type: 'text')]
    #[Groups(['watch_file:llm'])]
    private string $rationale;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['watch_file:llm'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: StrategicQuestion::class, inversedBy: 'searchQueries')]
    #[ORM\JoinColumn(nullable: false)]
    private StrategicQuestion $strategicQuestion;

    #[ORM\OneToMany(targetEntity: SearchResult::class, mappedBy: 'searchQuery', cascade: ['persist', 'remove'])]
    #[Groups(['watch_file:llm'])]
    private Collection $searchResults;

    public function __construct(
        string $searchTerm,
        string $country,
        string $language,
        string $queryType,
        string $rationale,
        StrategicQuestion $strategicQuestion,
    ) {
        Assert::length($country, 2);
        Assert::length($language, 2);

        $this->setSearchTerm($searchTerm);
        $this->country = $country;
        $this->language = $language;
        $this->queryType = $queryType;
        $this->rationale = $rationale;
        $this->strategicQuestion = $strategicQuestion;
        $this->createdAt = new \DateTimeImmutable();
        $this->searchResults = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSearchTerm(): string
    {
        return $this->searchTerm;
    }

    public function setSearchTerm(string $searchTerm): void
    {
        $this->searchTerm = $searchTerm;
        $this->updateSearchTermHash();
    }

    public static function makeSearchTermHash(string $searchTerm): string
    {
        return hash('xxh128', trim($searchTerm));
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateSearchTermHash(): void
    {
        $this->searchTermHash = self::makeSearchTermHash($this->searchTerm);
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getQueryType(): string
    {
        return $this->queryType;
    }

    public function getRationale(): string
    {
        return $this->rationale;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getStrategicQuestion(): StrategicQuestion
    {
        return $this->strategicQuestion;
    }

    public function getSearchResults(): Collection
    {
        return $this->searchResults;
    }

    public function addSearchResult(SearchResult $searchResult): void
    {
        if (!$this->searchResults->contains($searchResult)) {
            $this->searchResults->add($searchResult);
            $searchResult->setSearchQuery($this);
        }
    }

    public function removeSearchResult(SearchResult $searchResult): void
    {
        if ($this->searchResults->removeElement($searchResult)) {
            if ($searchResult->getSearchQuery() === $this) {
                $searchResult->setSearchQuery(null);
            }
        }
    }
}
