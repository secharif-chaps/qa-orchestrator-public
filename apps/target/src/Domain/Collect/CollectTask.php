<?php

declare(strict_types=1);

namespace App\Domain\Collect;

use App\Domain\Collect\Event\CollectTaskCompletedEvent;
use App\Domain\Collect\Event\CollectTaskResumedEvent;
use App\Domain\Collect\Event\CollectTaskStartedEvent;
use App\Domain\Source\Source;
use App\Domain\WatchFile\WatchFile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[ORM\Entity]
#[ORM\HasLifecycleCallbacks]
class CollectTask
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Source::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Source $source;

    #[ORM\ManyToOne(targetEntity: WatchFile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private WatchFile $watchFile;

    #[ORM\Column(length: 50)]
    private string $providerName;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerTaskId;

    #[ORM\Column(type: 'string', enumType: CollectTaskStatus::class, options: [
        'default' => CollectTaskStatus::CREATED,
    ])]
    private CollectTaskStatus $status;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $configuration;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        Source $source,
        WatchFile $watchFile,
        string $providerName,
        array $configuration = [],
        ?string $providerTaskId = null,
        CollectTaskStatus $status = CollectTaskStatus::CREATED,
    ) {
        $this->source = $source;
        $this->watchFile = $watchFile;
        $this->providerName = $providerName;
        $this->configuration = $configuration;
        $this->providerTaskId = $providerTaskId;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getWatchFile(): WatchFile
    {
        return $this->watchFile;
    }

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function getProviderTaskId(): ?string
    {
        return $this->providerTaskId;
    }

    public function getStatus(): CollectTaskStatus
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function start(string $providerTaskId, EventDispatcherInterface $eventDispatcher): void
    {
        $this->status->throwIfInvalidTransition(CollectTaskStatus::QUEUED);

        $this->providerTaskId = $providerTaskId;
        $this->status = CollectTaskStatus::QUEUED;
        $this->updatedAt = new \DateTimeImmutable();

        $eventDispatcher->dispatch(new CollectTaskStartedEvent($this));
    }

    public function resume(EventDispatcherInterface $eventDispatcher): void
    {
        $this->status->throwIfInvalidTransition(CollectTaskStatus::RUNNING);

        $this->status = CollectTaskStatus::RUNNING;
        $this->updatedAt = new \DateTimeImmutable();

        $eventDispatcher->dispatch(new CollectTaskResumedEvent($this));
    }

    public function complete(EventDispatcherInterface $eventDispatcher): void
    {
        $this->status->throwIfInvalidTransition(CollectTaskStatus::COMPLETED);

        $this->status = CollectTaskStatus::COMPLETED;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $eventDispatcher->dispatch(new CollectTaskCompletedEvent($this));
    }

    public function fail(EventDispatcherInterface $eventDispatcher): void
    {
        $this->status->throwIfInvalidTransition(CollectTaskStatus::FAILED);

        $this->status = CollectTaskStatus::FAILED;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();

        $eventDispatcher->dispatch(new CollectTaskCompletedEvent($this));
    }

    public function cancel(): void
    {
        $this->status->throwIfInvalidTransition(CollectTaskStatus::CANCELLED);

        $this->status = CollectTaskStatus::CANCELLED;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateStatus(CollectTaskStatus $status): void
    {
        $this->status->throwIfInvalidTransition($status);

        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCompleted(): bool
    {
        return $this->status->isCompleted();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function isCancelled(): bool
    {
        return $this->status->isCancelled();
    }

    /**
     * Store result data from a provider (e.g., Apify dataset).
     *
     * @param array<string, mixed> $result Serialized CollectTaskResult::toArray()
     */
    public function storeResult(array $result): void
    {
        $this->configuration['result'] = $result;
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
