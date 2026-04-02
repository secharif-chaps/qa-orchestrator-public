<?php

declare(strict_types=1);

namespace App\Domain\Document;

use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\UniqueConstraint(columns: ['user_id', 'document_id'])]
class DocumentSeenStatus
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: UuidType::NAME, nullable: false)]
    private ?Uuid $documentId = null;

    #[ORM\ManyToOne(targetEntity: WatchFile::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?WatchFile $watchFile = null;

    #[ORM\Column(nullable: false)]
    private ?\DateTimeImmutable $seenAt = null;

    public function __construct()
    {
        $this->seenAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getDocumentId(): ?Uuid
    {
        return $this->documentId;
    }

    public function setDocumentId(?Uuid $documentId): self
    {
        $this->documentId = $documentId;

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

    public function getSeenAt(): ?\DateTimeImmutable
    {
        return $this->seenAt;
    }

    public function setSeenAt(?\DateTimeImmutable $seenAt): self
    {
        $this->seenAt = $seenAt;

        return $this;
    }
}
