<?php

declare(strict_types=1);

namespace App\Domain\Document;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Webmozart\Assert\Assert;

readonly class AIValidation
{
    public function __construct(
        #[ApiProperty]
        #[Groups(['document:read', 'document:write', 'document:save'])]
        public AiValidationStatus $status,
        #[ApiProperty]
        #[Groups(['document:read', 'document:write', 'document:save'])]
        public int $confidenceScore,
        #[ApiProperty]
        #[Groups(['document:read', 'document:write', 'document:save'])]
        public ?ValidationReason $validationReason,
        #[ApiProperty]
        #[Groups(['document:read', 'document:write', 'document:save'])]
        public \DateTimeImmutable $processedAt,
        #[ApiProperty]
        #[Groups(['document:read', 'document:write', 'document:save'])]
        public ?string $referenceSubject = null,
    ) {
        Assert::range($confidenceScore, 0, 100, 'Confidence score must be between 0 and 100');
    }
}
