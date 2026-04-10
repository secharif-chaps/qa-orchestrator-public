<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation\Security;

use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class InternalJwtAuthenticator extends AbstractAuthenticator
{
    private const string HEADER_PREFIX = 'Internal ';
    private const string EXPECTED_ISSUER = 'global-gateway';

    /**
     * @param list<string> $allowedNetworks parsed CIDR networks; empty disables IP validation
     */
    public function __construct(
        private readonly UserGatewayInterface $userGateway,
        private readonly string $internalJwtSecret,
        private readonly LoggerInterface $logger,
        private readonly array $allowedNetworks = [],
    ) {
    }

    public static function create(
        UserGatewayInterface $userGateway,
        string $internalJwtSecret,
        LoggerInterface $logger,
        ?string $internalAllowedIps = null,
    ): self {
        $networks = array_values(array_filter(array_map('trim', explode(',', $internalAllowedIps ?? ''))));

        return new self($userGateway, $internalJwtSecret, $logger, $networks);
    }

    public function supports(Request $request): ?bool
    {
        $authHeader = $request->headers->get('Authorization', '');

        return str_starts_with($authHeader, self::HEADER_PREFIX);
    }

    public function authenticate(Request $request): Passport
    {
        $this->validateSourceIp($request);

        $authHeader = $request->headers->get('Authorization', '');
        $jwt = substr($authHeader, \strlen(self::HEADER_PREFIX));

        try {
            $decoded = JWT::decode($jwt, new Key($this->internalJwtSecret, 'HS256'));
        } catch (\Exception $e) {
            $this->logger->warning('Internal JWT decode failed.', [
                'error' => $e->getMessage(),
            ]);
            throw new CustomUserMessageAuthenticationException('Invalid internal token.');
        }

        /** @var array<string, mixed> $claims */
        $claims = (array) $decoded;

        $issuer = $claims['iss'] ?? null;
        if (self::EXPECTED_ISSUER !== $issuer) {
            throw new CustomUserMessageAuthenticationException('Invalid token issuer.');
        }

        $sub = $claims['sub'] ?? null;
        if (!\is_string($sub) || '' === $sub) {
            throw new CustomUserMessageAuthenticationException('Missing subject in token.');
        }

        return new SelfValidatingPassport(
            new UserBadge($sub, function (string $userId) {
                try {
                    return $this->userGateway->get($userId);
                } catch (UserNotFoundException) {
                    $this->logger->info('Internal JWT user not found, will be provisioned.', [
                        'sub' => $userId,
                    ]);
                    throw new CustomUserMessageAuthenticationException('User not found.');
                }
            }),
            [new InternalJwtClaimsBadge($claims)],
        );
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();

        $badge = $passport->getBadge(InternalJwtClaimsBadge::class);
        if (!$badge instanceof InternalJwtClaimsBadge) {
            throw new \LogicException('InternalJwtClaimsBadge manquant dans le passport.');
        }

        return new InternalJwtToken($user, $firewallName, array_values($user->getRoles()), $badge->getClaims());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            [
                'detail' => $exception->getMessageKey(),
            ],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    /**
     * Validate that the request originates from an allowed IP range.
     */
    private function validateSourceIp(Request $request): void
    {
        if ([] === $this->allowedNetworks) {
            return;
        }

        $clientIp = $request->headers->get('X-Forwarded-For');
        if (null !== $clientIp && '' !== $clientIp) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        } else {
            $clientIp = $request->getClientIp();
        }

        if (null === $clientIp || '' === $clientIp) {
            $this->logger->warning('Internal JWT: could not determine client IP.');
            throw new CustomUserMessageAuthenticationException('Could not determine source IP.');
        }

        if (IpUtils::checkIp($clientIp, $this->allowedNetworks)) {
            return;
        }

        $this->logger->warning('Internal JWT: IP not in allowlist.', [
            'client_ip' => $clientIp,
            'allowed_networks' => $this->allowedNetworks,
        ]);
        throw new CustomUserMessageAuthenticationException('Source IP not allowed.');
    }
}
