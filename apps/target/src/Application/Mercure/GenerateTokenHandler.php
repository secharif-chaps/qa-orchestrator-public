<?php

declare(strict_types=1);

namespace App\Application\Mercure;

use App\Domain\Shared\RealTimeTopicGeneratorInterface;
use App\Domain\User\UserGatewayInterface;
use App\UserInterface\Dto\Mercure\TokenDto;
use Firebase\JWT\JWT;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Generates Mercure JWT tokens for real-time subscriptions.
 *
 * This handler creates tokens with URI Templates for subscription claims,
 * enabling constant token size regardless of the number of resources a user
 * has access to. This implements ADR-2025-001 for scalable Mercure topics.
 *
 * Token is generated fresh on each call (no caching) for security:
 * - Fresh tokens reduce attack surface if a token is compromised
 * - Token generation is fast (<10ms) without database queries for resources
 * - Simpler code without cache invalidation concerns
 */
#[AsMessageHandler]
readonly class GenerateTokenHandler
{
    private const int TOKEN_DURATION = 3600; // Token duration in seconds (1 hour)

    public function __construct(
        private UserGatewayInterface $userGateway,
        private RealTimeTopicGeneratorInterface $topicGenerator,
        #[Autowire('%mercure.jwt.secret%')]
        private string $mercureJwtSecret,
    ) {
    }

    public function __invoke(GenerateTokenAction $action): TokenDto
    {
        $user = $this->userGateway->get($action->userId);

        $subscriptionTemplates = $this->topicGenerator->getSubscriptionTemplates($user);

        $currentTime = time();
        $expirationTime = $currentTime + self::TOKEN_DURATION;

        $payload = [
            'mercure' => [
                'subscribe' => $subscriptionTemplates,
                'publish' => [], // No publishing rights for subscribers
            ],
            'sub' => $user->getUserIdentifier(),
            'iat' => $currentTime,
            'exp' => $expirationTime,
        ];

        $jwtToken = JWT::encode($payload, $this->mercureJwtSecret, 'HS256');

        return new TokenDto($jwtToken, $expirationTime);
    }
}
