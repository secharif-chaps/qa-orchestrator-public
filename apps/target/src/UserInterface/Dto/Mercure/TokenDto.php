<?php

declare(strict_types=1);

namespace App\UserInterface\Dto\Mercure;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\OpenApi\Model;
use App\Infrastructure\Mercure\MercureTokenProvider;

#[ApiResource(
    shortName: 'RealTimeToken',
    operations: [
        new Get(
            uriTemplate: '/security/real-time/token',
            openapi: new Model\Operation(
                summary: 'Generate Mercure JWT token',
                description: 'Generates a JWT token for Mercure real-time communication. The token contains subscription permissions for the user\'s watch files and expires in 1 hour. The token only allows subscriptions to watchfiles accessible by the authenticated user; it does not grant publish rights or access to other users\' resources.'
            ),
            security: 'is_granted("ROLE_USER")',
            output: TokenDto::class,
            provider: MercureTokenProvider::class,
        ),
    ]
)]
final readonly class TokenDto
{
    public function __construct(
        public string $token,
        public int $expires_at,
    ) {
    }
}
