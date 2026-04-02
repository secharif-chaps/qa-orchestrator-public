<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Chat;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

readonly class UserMessageDto
{
    public function __construct(
        #[Groups(['message:write'])]
        #[Assert\NotBlank]
        #[ApiProperty(
            description: 'The message text to send to the AI assistant',
            example: 'What are the latest news about this topic?',
        )]
        public string $content,
    ) {
    }
}
