<?php

declare(strict_types=1);

namespace App\Domain\Document;

use App\Domain\User\User;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Immutable audit trail record of a manual document validation action.
 *
 * Each validation (accept/refuse) creates a new record, enabling a complete
 * and non-modifiable history of validation decisions. Status changes on a
 * document create multiple DocumentValidation entries.
 *
 * @see Document::manuallyValidate()
 */
#[ORM\Entity]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['document_id', 'created_at'])]
class DocumentValidation
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(type: UuidType::NAME, nullable: false)]
    private Uuid $documentId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(enumType: ManualValidationStatus::class)]
    private ManualValidationStatus $action;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Uuid $documentId, User $user, ManualValidationStatus $action)
    {
        $this->documentId = $documentId;
        $this->user = $user;
        $this->action = $action;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getDocumentId(): Uuid
    {
        return $this->documentId;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getAction(): ManualValidationStatus
    {
        return $this->action;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
