<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Security;

use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Uid\Uuid;

class TestAuthenticator extends AbstractAuthenticator
{
    public const string HEADER_TEST_AUTH_USER_ID = 'X-Test-User-Id';

    public function __construct(
        private UserGatewayInterface $userGateway,
        private readonly string $environment,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $this->ensureIsTestEnvironment();

        return $request->headers->has(self::HEADER_TEST_AUTH_USER_ID);
    }

    public function authenticate(Request $request): Passport
    {
        $this->ensureIsTestEnvironment();

        $userId = $request->headers->get(self::HEADER_TEST_AUTH_USER_ID);

        if (!$userId) {
            throw new CustomUserMessageAuthenticationException('No test user ID provided');
        }

        if (!Uuid::isValid($userId)) {
            throw new CustomUserMessageAuthenticationException("Invalid test user ID format: {$userId}");
        }

        return new SelfValidatingPassport(
            new UserBadge($userId, function (string $userId): UserInterface {
                try {
                    return $this->userGateway->get($userId);
                } catch (UserNotFoundException $e) {
                    throw new AuthenticationException("Test user not found: {$userId}", previous: $e);
                }
            }),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }

    private function ensureIsTestEnvironment(): void
    {
        if ('test' !== $this->environment) {
            throw new \LogicException('TestAuthenticator should only be used in the test environment.');
        }
    }
}
