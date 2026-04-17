<?php

declare(strict_types=1);

namespace App\Infrastructure\User\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class ApifyWebhookAuthenticator extends AbstractAuthenticator
{
    private const string JWT_ALGO = 'HS256';

    public function __construct(
        #[\SensitiveParameter]
        #[Autowire('%app.collect_task.jwt_secret%')]
        private readonly string $jwtSecret,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return '/api/apify/webhook' === $request->getPathInfo() && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $token = $request->query->getString('token');

        if ('' === $token) {
            throw new CustomUserMessageAuthenticationException('Missing JWT token in query parameter');
        }

        try {
            $payload = JWT::decode($token, new Key($this->jwtSecret, self::JWT_ALGO));
        } catch (\UnexpectedValueException $e) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT token: ' . $e->getMessage());
        }

        if (!isset($payload->collect_task_id) || !\is_string($payload->collect_task_id)) {
            throw new CustomUserMessageAuthenticationException('JWT token missing collect_task_id claim');
        }

        // Store collect_task_id in request attributes for the controller
        $request->attributes->set('collect_task_id', $payload->collect_task_id);

        // Create a virtual user with the Apify webhook role
        $user = new class implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_APIFY_WEBHOOK'];
            }

            public function getUserIdentifier(): string
            {
                return 'apify_webhook';
            }

            public function eraseCredentials(): void
            {
            }
        };

        return new SelfValidatingPassport(
            new UserBadge('apify_webhook', fn () => $user),
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName,
    ): ?JsonResponse {
        return null; // Let the request go to the controller
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?JsonResponse
    {
        return new JsonResponse([
            'error' => $exception->getMessageKey(),
        ], 401);
    }
}
