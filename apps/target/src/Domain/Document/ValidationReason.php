<?php

declare(strict_types=1);

namespace App\Domain\Document;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;

readonly class ValidationReason
{
    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    public ?string $fr;

    #[ApiProperty]
    #[Groups(['document:read', 'document:write', 'document:save'])]
    public ?string $en;

    public function __construct(?string $fr = null, ?string $en = null)
    {
        $this->fr = $fr;
        $this->en = $en;
    }

    public function isEmpty(): bool
    {
        return null === $this->fr && null === $this->en;
    }

    /**
     * @return array<string, string|null>|null
     */
    public function toArray(): ?array
    {
        if ($this->isEmpty()) {
            return null;
        }

        return [
            'fr' => $this->fr,
            'en' => $this->en,
        ];
    }

    /**
     * @param array<string, string|null>|null $data
     */
    public static function fromArray(?array $data): ?self
    {
        if (null === $data) {
            return null;
        }

        return new self($data['fr'] ?? null, $data['en'] ?? null);
    }
}
