<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Document;

use ApiPlatform\Metadata\ApiProperty;
use App\Domain\Document\ManualValidationStatus;
use App\Infrastructure\Shared\Constraint\EnumConstraint;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

readonly class ManualValidateDocumentInputDto
{
    public function __construct(
        #[Groups(['document:manual-validate'])]
        #[Assert\NotBlank(message: 'validation.action.not_blank', groups: ['document:manual-validate'])]
        #[EnumConstraint(
            enumClass: ManualValidationStatus::class,
            message: 'validation.action.invalid_choice',
            groups: ['document:manual-validate'])]
        #[ApiProperty(
            description: 'The validation action to apply to the document. Use "accept" to validate the document as relevant, "refuse" to mark it as irrelevant, or "uncertain" to flag it for review.',
            example: 'accept',
            openapiContext: [
                'type' => 'string',
                'enum' => ['accept', 'refuse', 'uncertain'],
            ],
        )]
        public string $action,
    ) {
    }

    public function getManualValidationStatus(): ManualValidationStatus
    {
        return ManualValidationStatus::from($this->action);
    }
}
