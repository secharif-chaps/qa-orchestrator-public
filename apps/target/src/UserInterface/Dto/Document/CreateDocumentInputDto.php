<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Document;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

readonly class CreateDocumentInputDto
{
    public function __construct(
        #[Groups(['document:create'])]
        #[Assert\Url(requireTld: true)]
        public ?string $url = null,

        #[Groups(['document:create'])]
        #[Assert\Length(max: 5_000_000, maxMessage: 'HTML content must not exceed 5MB.')]
        public ?string $html = null,

        #[Groups(['document:create'])]
        #[Assert\Uuid]
        public ?string $sourceId = null,

        #[Groups(['document:create'])]
        #[Assert\Length(max: 500)]
        public ?string $title = null,

        #[Groups(['document:create'])]
        #[Assert\Length(max: 1000)]
        public ?string $excerpt = null,
    ) {
    }

    #[Assert\Callback]
    public function validateAtLeastOneContent(ExecutionContextInterface $context): void
    {
        if (null === $this->url && null === $this->html) {
            $context->buildViolation('At least one of "url" or "html" must be provided.')
                ->atPath('url')
                ->addViolation();
        }
    }
}
