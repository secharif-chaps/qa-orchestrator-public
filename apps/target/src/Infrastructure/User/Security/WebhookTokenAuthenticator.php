<?php

namespace App\Infrastructure\User\Security;

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

class WebhookTokenAuthenticator extends AbstractAuthenticator
{
    /**
     * @param string[] $allowedIps
     */
    public function __construct(
        private readonly string $sharedToken,
        private readonly string $secretKey,
        private readonly array $allowedIps,
    ) {
    }

    public static function create(string $sharedToken, string $secretKey, string $allowedIpsString): self
    {
        $allowedIps = array_filter(array_map('trim', explode(',', $allowedIpsString)));

        return new self($sharedToken, $secretKey, $allowedIps);
    }

    public function supports(Request $request): ?bool
    {
        return '/api/webhook' === $request->getPathInfo() && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $clientIp = $request->getClientIp();
        if (!$this->isIpAllowed($clientIp)) {
            throw new CustomUserMessageAuthenticationException('Invalid IP address');
        }

        $token = $request->headers->get('X-API-TOKEN');
        if ($token !== $this->sharedToken) {
            throw new CustomUserMessageAuthenticationException('Invalid API token');
        }

        $payload = $request->getContent();
        $signature = $request->headers->get('X-Signature');
        $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);

        if (empty($signature) || !hash_equals($expectedSignature, $signature)) {
            throw new CustomUserMessageAuthenticationException('Invalid signature');
        }

        // Create a virtual user with webhook role
        $user = new class implements UserInterface {
            public function getRoles(): array
            {
                return ['ROLE_WEBHOOK'];
            }

            public function getUserIdentifier(): string
            {
                return 'webhook';
            }

            public function eraseCredentials(): void
            {
            }
        };

        return new SelfValidatingPassport(
            new UserBadge('webhook', fn () => $user)
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

    /**
     * Check if the given IP address is allowed.
     *
     * Supports both individual IP addresses and CIDR ranges.
     * Examples: "127.0.0.1", "172.18.0.0/16", "10.0.0.0/8"
     */
    private function isIpAllowed(?string $clientIp): bool
    {
        if (null === $clientIp) {
            return false;
        }

        foreach ($this->allowedIps as $allowedIp) {
            // Check if it's a CIDR range
            if (str_contains($allowedIp, '/')) {
                if ($this->ipMatchesCidr($clientIp, $allowedIp)) {
                    return true;
                }
            } else {
                // Exact IP match
                if ($clientIp === $allowedIp) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if an IP address matches a CIDR range.
     *
     * @param string $ip   The IP address to check (e.g., "172.18.0.5")
     * @param string $cidr The CIDR range (e.g., "172.18.0.0/16")
     */
    private function ipMatchesCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        // Convert IP addresses to long integers
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if (false === $ipLong || false === $subnetLong) {
            return false;
        }

        // Calculate the network mask
        $maskLong = -1 << (32 - (int) $mask);

        // Check if the IP is within the subnet
        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }
}
