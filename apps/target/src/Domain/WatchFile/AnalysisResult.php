<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\Shared\HasRequiredWatchFileInterface;
use App\Domain\Shared\TranslatedText;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
class AnalysisResult implements HasRequiredWatchFileInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[Groups(['watch_file:llm'])]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?string $content = null;

    #[ORM\ManyToOne(targetEntity: WatchFile::class, inversedBy: 'analysisResults')]
    #[ORM\JoinColumn(nullable: false)]
    private WatchFile $watchFile;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file:llm'])]
    private array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['watch_file:llm'])]
    private \DateTimeImmutable $createdAt;

    /**
     * @var array<int, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file:llm'])]
    private array $identifiedNeeds = [];

    /**
     * @var array<int, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file:llm'])]
    private array $entities = [];

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file:llm'])]
    private array $temporalScope = [];

    /**
     * @var array<int, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file:llm'])]
    private array $monitoringTypes = [];

    /**
     * @var array<int, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file:llm'])]
    private array $strategicQuestions = [];

    /**
     * @var array<int, mixed>
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['watch_file:llm'])]
    private array $suggestedApproach = [];

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?int $confidenceScore = null;

    // WatchFile Classification Fields (flattened from classification workflow)

    #[ORM\Column(type: 'string', length: 50, nullable: true, enumType: MonitoringType::class)]
    #[Groups(['watch_file:llm'])]
    private ?MonitoringType $primaryClassificationType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?int $primaryClassificationConfidence = null;

    #[ORM\Column(type: 'translated_text', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?TranslatedText $primaryClassificationJustification = null;

    /**
     * @var array<int, array{type: string, confidence: int, justification: array{en: string, fr: string}}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?array $secondaryClassificationTypes = null;

    /**
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?array $classificationKeywords = null;

    /**
     * @var list<string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?array $classificationDetectedEntities = null;

    #[ORM\Column(type: 'string', length: 1000, nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?string $classificationUserObjective = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?string $geographicScope = null;

    /**
     * @var list<array{name: string, type: string, relevance: string, score: int, url: string|null}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?array $sourceSuggestions = null;

    /**
     * @var list<array{name: string, type: string, relevance: string, score: int}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?array $actorSuggestions = null;

    /**
     * @var list<array{label: string, keywords: list<string>, relevanceScore: int, searchQueryTemplate: string}>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['watch_file:llm'])]
    private ?array $classificationTopics = null;

    #[ORM\Embedded(class: DeepSearchReadiness::class)]
    #[Groups(['watch_file:llm'])]
    private ?DeepSearchReadiness $deepSearchReadiness = null;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(WatchFile $watchFile, ?string $content = null, array $metadata = [])
    {
        $this->id = Uuid::v4();
        $this->content = $content;
        $this->watchFile = $watchFile;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();

        // Ensure bidirectional association
        $watchFile->addAnalysisResult($this);
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<int, mixed>
     */
    public function getIdentifiedNeeds(): array
    {
        return $this->identifiedNeeds;
    }

    /**
     * @param array<int, mixed> $identifiedNeeds
     */
    public function setIdentifiedNeeds(array $identifiedNeeds): void
    {
        $this->identifiedNeeds = $identifiedNeeds;
    }

    /**
     * @return array<int, mixed>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @param array<int, mixed> $entities
     */
    public function setEntities(array $entities): void
    {
        $this->entities = $entities;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTemporalScope(): array
    {
        return $this->temporalScope;
    }

    /**
     * @param array<string, mixed> $temporalScope
     */
    public function setTemporalScope(array $temporalScope): void
    {
        $this->temporalScope = $temporalScope;
    }

    /**
     * @return array<int, mixed>
     */
    public function getMonitoringTypes(): array
    {
        return $this->monitoringTypes;
    }

    /**
     * @param array<int, mixed> $monitoringTypes
     */
    public function setMonitoringTypes(array $monitoringTypes): void
    {
        $this->monitoringTypes = $monitoringTypes;
    }

    /**
     * @return array<int, mixed>
     */
    public function getStrategicQuestions(): array
    {
        return $this->strategicQuestions;
    }

    /**
     * @param array<int, mixed> $strategicQuestions
     */
    public function setStrategicQuestions(array $strategicQuestions): void
    {
        $this->strategicQuestions = $strategicQuestions;
    }

    /**
     * @return array<int, mixed>
     */
    public function getSuggestedApproach(): array
    {
        return $this->suggestedApproach;
    }

    /**
     * @param array<int, mixed> $suggestedApproach
     */
    public function setSuggestedApproach(array $suggestedApproach): void
    {
        $this->suggestedApproach = $suggestedApproach;
    }

    public function getConfidenceScore(): ?int
    {
        return $this->confidenceScore;
    }

    public function setConfidenceScore(?int $confidenceScore): void
    {
        $this->confidenceScore = $confidenceScore;
    }

    // Classification Data Getters/Setters

    public function getPrimaryClassificationType(): ?MonitoringType
    {
        return $this->primaryClassificationType;
    }

    public function setPrimaryClassificationType(?MonitoringType $type): void
    {
        $this->primaryClassificationType = $type;
    }

    public function getPrimaryClassificationConfidence(): ?int
    {
        return $this->primaryClassificationConfidence;
    }

    public function setPrimaryClassificationConfidence(?int $confidence): void
    {
        $this->primaryClassificationConfidence = $confidence;
    }

    public function getPrimaryClassificationJustification(): ?TranslatedText
    {
        return $this->primaryClassificationJustification;
    }

    public function setPrimaryClassificationJustification(?TranslatedText $justification): void
    {
        $this->primaryClassificationJustification = $justification;
    }

    /**
     * @return array<int, array{type: string, confidence: int, justification: array{en: string, fr: string}}>|null
     */
    public function getSecondaryClassificationTypes(): ?array
    {
        return $this->secondaryClassificationTypes;
    }

    /**
     * @param array<int, array{type: string, confidence: int, justification: array{en: string, fr: string}}>|null $types
     */
    public function setSecondaryClassificationTypes(?array $types): void
    {
        $this->secondaryClassificationTypes = $types;
    }

    /**
     * @return list<string>|null
     */
    public function getClassificationKeywords(): ?array
    {
        return $this->classificationKeywords;
    }

    /**
     * @param list<string>|null $keywords
     */
    public function setClassificationKeywords(?array $keywords): void
    {
        $this->classificationKeywords = $keywords;
    }

    /**
     * @return list<string>|null
     */
    public function getClassificationDetectedEntities(): ?array
    {
        return $this->classificationDetectedEntities;
    }

    /**
     * @param list<string>|null $entities
     */
    public function setClassificationDetectedEntities(?array $entities): void
    {
        $this->classificationDetectedEntities = $entities;
    }

    public function getClassificationUserObjective(): ?string
    {
        return $this->classificationUserObjective;
    }

    public function setClassificationUserObjective(?string $objective): void
    {
        $this->classificationUserObjective = $objective;
    }

    public function getGeographicScope(): ?string
    {
        return $this->geographicScope;
    }

    public function setGeographicScope(?string $geographicScope): void
    {
        $this->geographicScope = $geographicScope;
    }

    /**
     * @return list<array{name: string, type: string, relevance: string, score: int, url: string|null}>|null
     */
    public function getSourceSuggestions(): ?array
    {
        return $this->sourceSuggestions;
    }

    /**
     * @param list<array{name: string, type: string, relevance: string, score: int, url: string|null}>|null $suggestions
     */
    public function setSourceSuggestions(?array $suggestions): void
    {
        $this->sourceSuggestions = $suggestions;
    }

    /**
     * @return list<array{name: string, type: string, relevance: string, score: int}>|null
     */
    public function getActorSuggestions(): ?array
    {
        return $this->actorSuggestions;
    }

    /**
     * @param list<array{name: string, type: string, relevance: string, score: int}>|null $suggestions
     */
    public function setActorSuggestions(?array $suggestions): void
    {
        $this->actorSuggestions = $suggestions;
    }

    /**
     * @return list<array{label: string, keywords: list<string>, relevanceScore: int, searchQueryTemplate: string}>|null
     */
    public function getClassificationTopics(): ?array
    {
        return $this->classificationTopics;
    }

    /**
     * @param list<array{label: string, keywords: list<string>, relevanceScore: int, searchQueryTemplate: string}>|null $topics
     */
    public function setClassificationTopics(?array $topics): void
    {
        $this->classificationTopics = $topics;
    }

    public function getDeepSearchReadiness(): ?DeepSearchReadiness
    {
        return $this->deepSearchReadiness;
    }

    public function setDeepSearchReadiness(?DeepSearchReadiness $readiness): void
    {
        $this->deepSearchReadiness = $readiness;
    }

    /**
     * Helper method to set all classification data from WatchFileTypeClassificationResult.
     */
    public function setClassificationData(WatchFileTypeClassificationResult $result): void
    {
        $this->setPrimaryClassificationType($result->primaryType->type);
        $this->setPrimaryClassificationConfidence($result->primaryType->confidenceScore);
        $this->setPrimaryClassificationJustification($result->primaryType->justification);

        $secondaryTypes = [];
        foreach ($result->secondaryTypes as $secondary) {
            $secondaryTypes[] = [
                'type' => $secondary->type->value,
                'confidence' => $secondary->confidenceScore,
                'justification' => $secondary->justification->toArray(),
            ];
        }
        $this->setSecondaryClassificationTypes($secondaryTypes);

        // Store topics
        $topics = [];
        foreach ($result->topics as $topic) {
            $topics[] = $topic->toArray();
        }
        $this->setClassificationTopics($topics);

        $this->setClassificationKeywords($result->keywords);
        $this->setClassificationDetectedEntities($result->detectedEntities);
        $this->setClassificationUserObjective($result->userObjective);
        $this->setGeographicScope($result->geographicScope);

        // Store source suggestions with full details
        $sourceSuggestions = [];
        foreach ($result->sourceSuggestions as $source) {
            $sourceSuggestions[] = $source->toArray();
        }
        $this->setSourceSuggestions($sourceSuggestions);

        // Store actor suggestions with full details
        $actorSuggestions = [];
        foreach ($result->actorSuggestions as $actor) {
            $actorSuggestions[] = $actor->toArray();
        }
        $this->setActorSuggestions($actorSuggestions);

        $this->setDeepSearchReadiness($result->deepSearchReadiness);
    }
}
