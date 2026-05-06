<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\WatchFile;

use ApiPlatform\Metadata\ApiProperty;
use App\Domain\WatchFile\WatchFile;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

class ShareWatchFileInputDto
{
    /** @param list<ShareUserInputDto> $member */
    public function __construct(
        #[Groups(['watch_file_user:write'])]
        #[Assert\NotBlank()]
        #[Assert\Count(min: 1, max: WatchFile::MAX_WATCHFILE_USERS)]
        #[Assert\All([new Assert\Type(ShareUserInputDto::class)])]
        #[ApiProperty(
            description: 'List of users to share the watchfile with',
            openapiContext: [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'userId' => [
                            'type' => 'string',
                            'description' => 'The ID of the user to share the watchfile with',
                            'example' => '550e8400-e29b-41d4-a716-446655440000',
                        ],
                        'role' => [
                            'type' => 'string',
                            'description' => 'Role of the user in the watchfile sharing context. ' .
                                '`viewer`: Can view the watchfile contents but cannot make changes. ' .
                                '`editor`: Can view and edit the watchfile contents.',
                            'example' => 'viewer',
                            'enum' => ['editor', 'viewer'],
                        ],
                    ],
                ],
            ]
        )]
        public readonly array $member,
    ) {
    }
}
