<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\WatchFile;

use App\Domain\WatchFile\WatchFileUserRole;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ShareUserInputDto
{
    public function __construct(
        #[Groups(['watch_file_user:read', 'watch_file_user:write'])]
        #[Assert\NotBlank()]
        #[Assert\Uuid()]
        public readonly string $userId,

        #[Groups(['watch_file_user:read', 'watch_file_user:write'])]
        #[Assert\NotBlank()]
        #[Assert\Choice(callback: [WatchFileUserRole::class, 'editableValues'])]
        public readonly string $role,
    ) {
    }
}
