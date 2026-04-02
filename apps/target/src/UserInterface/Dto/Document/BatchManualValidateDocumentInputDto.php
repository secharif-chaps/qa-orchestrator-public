<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Document;

use App\Domain\Document\ManualValidationStatus;
use App\Infrastructure\Shared\Constraint\EnumConstraint;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

readonly class BatchManualValidateDocumentInputDto
{
    /**
     * @param array<int, string> $documentIds
     */
    public function __construct(
        #[Groups(['document:batch-manual-validate'])]
        #[SerializedName('document_ids')]
        #[Assert\NotBlank(message: 'validation.document_ids.not_blank', groups: ['document:batch-manual-validate'])]
        #[Assert\Type(type: 'array', message: 'validation.document_ids.must_be_array', groups: [
            'document:batch-manual-validate',
        ])]
        #[Assert\Count(min: 1, max: 50, groups: ['document:batch-manual-validate'])]
        #[Assert\All([
            new Assert\NotBlank(message: 'validation.document_id.not_blank'),
            new Assert\Uuid(message: 'validation.document_id.invalid_uuid'),
        ], groups: ['document:batch-manual-validate'])]
        public array $documentIds,
        #[Groups(['document:batch-manual-validate'])]
        #[Assert\NotBlank(message: 'validation.action.not_blank', groups: ['document:batch-manual-validate'])]
        #[EnumConstraint(
            enumClass: ManualValidationStatus::class,
            message: 'validation.action.invalid_choice',
            groups: ['document:batch-manual-validate']
        )]
        public string $action,
    ) {
    }

    public function getManualValidationStatus(): ManualValidationStatus
    {
        return ManualValidationStatus::from($this->action);
    }
}
