<?php

declare(strict_types=1);

namespace App\Domain\WatchFileEvent;

use ApiPlatform\Elasticsearch\Filter\OrderFilter;
use ApiPlatform\Elasticsearch\Filter\TermFilter;
use ApiPlatform\Elasticsearch\State\Options;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Link;
use ApiPlatform\OpenApi\Model;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\OpenSearch\Filter\EventDateFilter;
use App\Infrastructure\OpenSearch\Filter\EventTypeFilter;
use App\Infrastructure\OpenSearch\Filter\NestedActorFilter;
use App\Infrastructure\OpenSearch\Filter\TitleMatchFilter;
use App\Infrastructure\WatchFileEvent\EventsGraphProvider;
use App\Infrastructure\WatchFileEvent\WatchFileEventCollectionProvider;
use App\Infrastructure\WatchFileEvent\WatchFileEventItemProvider;
use App\UserInterface\Dto\WatchFileEvent\EventGraphEntryDto;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/watch_files/{watchFileId}/events',
            uriVariables: [
                'watchFileId' => new Link(fromClass: WatchFile::class, identifiers: ['id']),
            ],
            name: 'get_watch_file_events',
            openapi: new Model\Operation(
                summary: 'Retrieves watch file events',
                description: 'Returns a paginated list of watch file events. Each event contains metadata, actors, document links, and processing status information. Events can be filtered by date range to find all events that overlap with the specified timeframe.',
                parameters: [
                    new Model\Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        description: 'The unique identifier of the watch file',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'startDate',
                        in: 'query',
                        description: 'Start of the date range filter. Returns events that overlap with this timeframe (events where endDate >= startDate or endDate is null). Use ISO 8601 format.',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'format' => 'date-time',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'endDate',
                        in: 'query',
                        description: 'End of the date range filter. Returns events that overlap with this timeframe (events where startDate <= endDate). Use ISO 8601 format.',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'format' => 'date-time',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'actors.id',
                        in: 'query',
                        description: 'Filter events by actor ID',
                        required: false,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'eventType',
                        in: 'query',
                        description: 'Filter events by event type. Accepts a single value (e.g., ?eventType=commercial_business). Valid values: commercial_business, financial, organizational_hr, technological_rd, regulatory_political, market_competitors, societal_environmental.',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'example' => EventType::COMMERCIAL_BUSINESS->value,
                        ],
                    ),
                ],
            ),
            provider: WatchFileEventCollectionProvider::class,
            stateOptions: new Options(index: self::INDEX_NAME),
        ),
        new Get(
            openapi: new Model\Operation(
                summary: 'Retrieves a specific watch file event',
                description: 'Returns detailed information about a single watch file event including its metadata, actors, document links, and processing status.',
                parameters: [
                    new Model\Parameter(
                        name: 'id',
                        in: 'path',
                        description: 'The unique identifier of the watch file event to retrieve',
                        required: true,
                        schema: [
                            'type' => 'string',
                        ],
                    ),
                ],
            ),
            provider: WatchFileEventItemProvider::class,
            stateOptions: new Options(index: self::INDEX_NAME),
        ),
        new GetCollection(
            uriTemplate: '/watch_files/{watchFileId}/events/graph',
            uriVariables: [
                'watchFileId' => new Link(fromClass: WatchFile::class, identifiers: ['id']),
            ],
            openapi: new Model\Operation(
                summary: 'Retrieves watch file events graph data',
                description: 'Returns aggregated data about events and documents per time period. Each entry contains the number of documents (based on dateCollect), events (counted for each period they span), and whether the period has events, along with the start and end dates of the period. Events are counted in all time periods they overlap with, based on their startDate and endDate (or ongoing if endDate is null).',
                parameters: [
                    new Model\Parameter(
                        name: 'watchFileId',
                        in: 'path',
                        description: 'The unique identifier of the watch file',
                        required: true,
                        schema: [
                            'type' => 'string',
                            'format' => 'uuid',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'interval',
                        in: 'query',
                        description: 'Time interval for aggregation (1h, 1d, 1w, 1M, 1y). Defaults to 1d (daily).',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'enum' => ['1h', '1d', '1w', '1M', '1y'],
                            'default' => '1d',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'startDate',
                        in: 'query',
                        description: 'Start date for filtering (ISO 8601 format). If null, returns all data from the beginning.',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'format' => 'date-time',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'endDate',
                        in: 'query',
                        description: 'End date for filtering (ISO 8601 format). If null, returns all data up to now.',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'format' => 'date-time',
                        ],
                    ),
                    new Model\Parameter(
                        name: 'actors.id',
                        in: 'query',
                        description: 'Filter by actor IDs. When provided, only events with these actors are counted, and document counts come from event documentLinks instead of the documents index. Can be a single ID or array of IDs.',
                        required: false,
                        schema: [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ),
                    new Model\Parameter(
                        name: 'eventType',
                        in: 'query',
                        description: 'Filter events by event type. When provided, only events of the specified type(s) are counted. Can be a single value or array of values (e.g., ?eventType=commercial_business or ?eventType[]=commercial_business&eventType[]=financial). Valid values: commercial_business, financial, organizational_hr, technological_rd, regulatory_political, market_competitors, societal_environmental.',
                        required: false,
                        schema: [
                            'type' => 'string',
                            'example' => EventType::COMMERCIAL_BUSINESS->value,
                        ],
                    ),
                ],
            ),
            paginationEnabled: false,
            normalizationContext: [
                'groups' => ['event_graph:read'],
            ],
            output: EventGraphEntryDto::class,
            provider: EventsGraphProvider::class,
        ),
    ],
    normalizationContext: [
        'groups' => ['watch_file_event:read'],
    ],
)]
#[ApiFilter(TermFilter::class, strategy: 'exact', properties: ['watchFile.id', 'eventType'])]
#[ApiFilter(NestedActorFilter::class)]
#[ApiFilter(EventDateFilter::class)]
#[ApiFilter(EventTypeFilter::class)]
#[ApiFilter(TitleMatchFilter::class, properties: ['title.fr', 'title.en'])]
#[ApiFilter(OrderFilter::class, properties: [
    'startDate' => 'asc',
    'endDate' => 'asc',
])]
class WatchFileEvent
{
    public const string INDEX_NAME = 'watch_file_events';
    public const int MAX_TITLE_LENGTH = 100;

    #[ApiProperty(identifier: true)]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private string $id;

    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private TranslatedText $title;

    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private \DateTimeImmutable $startDate;

    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private ?\DateTimeImmutable $endDate = null;

    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private TranslatedText $description;

    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private EventType $eventType;

    /**
     * @var Collection<int, EventActor>|null
     */
    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private ?Collection $actors = null;

    /**
     * @var Collection<int, DocumentLink>|null
     */
    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private ?Collection $documentLinks = null;

    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private ExtractionStatus $extractionStatus;

    #[ApiProperty]
    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private \DateTimeImmutable $createdAt;

    #[Groups(['watch_file_event:read', 'watch_file_event:save'])]
    private ?WatchFile $watchFile = null;

    /**
     * @param array<int, EventActor>   $actors
     * @param array<int, DocumentLink> $documentLinks
     */
    public function __construct(
        ?string $id,
        \DateTimeImmutable $startDate,
        TranslatedText $description,
        EventType $eventType,
        WatchFile $watchFile,
        array $actors,
        array $documentLinks,
        TranslatedText $title,
        ExtractionStatus $extractionStatus = ExtractionStatus::PENDING,
        ?\DateTimeImmutable $endDate = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->id = $id ?? Uuid::v4()->toString();
        $this->title = $title;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->description = $description;
        $this->eventType = $eventType;
        $this->actors = new ArrayCollection($actors);
        $this->documentLinks = new ArrayCollection($documentLinks);
        $this->extractionStatus = $extractionStatus;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->watchFile = $watchFile;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): TranslatedText
    {
        return $this->title;
    }

    public function setTitle(TranslatedText $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getDescription(): TranslatedText
    {
        return $this->description;
    }

    public function setDescription(TranslatedText $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getEventType(): EventType
    {
        return $this->eventType;
    }

    public function setEventType(EventType $eventType): self
    {
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * @return Collection<int, EventActor>|null
     */
    public function getActors(): ?Collection
    {
        return $this->actors;
    }

    /**
     * @param Collection<int, EventActor> $actors
     */
    public function setActors(Collection $actors): self
    {
        $this->actors = $actors;

        return $this;
    }

    public function addActor(EventActor $actor): self
    {
        $this->actors ??= new ArrayCollection();
        $this->actors->add($actor);

        return $this;
    }

    public function removeActor(EventActor $actor): self
    {
        $this->actors ??= new ArrayCollection();
        $this->actors->removeElement($actor);

        return $this;
    }

    /**
     * @return ?Collection<int, DocumentLink>
     */
    public function getDocumentLinks(): ?Collection
    {
        return $this->documentLinks;
    }

    /**
     * @param Collection<int, DocumentLink> $documentLinks
     */
    public function setDocumentLinks(Collection $documentLinks): self
    {
        $this->documentLinks = $documentLinks;

        return $this;
    }

    public function addDocumentLink(DocumentLink $documentLink): self
    {
        $this->documentLinks ??= new ArrayCollection();
        $this->documentLinks->add($documentLink);

        return $this;
    }

    public function removeDocumentLink(DocumentLink $documentLink): self
    {
        $this->documentLinks ??= new ArrayCollection();
        $this->documentLinks->removeElement($documentLink);

        return $this;
    }

    public function getExtractionStatus(): ExtractionStatus
    {
        return $this->extractionStatus;
    }

    public function setExtractionStatus(ExtractionStatus $extractionStatus): self
    {
        $this->extractionStatus = $extractionStatus;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getWatchFile(): ?WatchFile
    {
        return $this->watchFile;
    }

    public function setWatchFile(?WatchFile $watchFile): self
    {
        $this->watchFile = $watchFile;

        return $this;
    }
}
