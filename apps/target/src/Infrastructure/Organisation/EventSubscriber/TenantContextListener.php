<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation\EventSubscriber;

use App\Application\Organisation\OrganisationSyncService;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Infrastructure\Organisation\Security\InternalJwtToken;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 10)]
readonly class TenantContextListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private OrganisationSyncService $organisationSyncService,
        private UserGatewayInterface $userGateway,
        private TenantContext $tenantContext,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Path 1: Internal JWT from Global Service (production flow)
        if ($token instanceof InternalJwtToken) {
            $this->handleInternalJwt($token, $user);

            return;
        }

        // Path 2: Keycloak OIDC token (local dev fallback)
        $this->handleKeycloakFallback($event, $user);
    }

    private function handleInternalJwt(InternalJwtToken $token, User $user): void
    {
        $claims = $token->getClaims();

        $orgId = $claims['org_id'] ?? null;
        if (!\is_string($orgId) || '' === $orgId) {
            $this->logger->warning('Internal JWT missing org_id claim.');

            return;
        }

        $orgName = isset($claims['org_name']) && \is_string($claims['org_name'])
            ? $claims['org_name']
            : $orgId;

        $normalised = [
            'org_id' => $orgId,
            'org_name' => $orgName,
        ];
        $organisation = $this->organisationSyncService->syncFromToken($user, $normalised);

        $impersonator = null;
        if (isset($claims['impersonator']) && \is_array($claims['impersonator'])) {
            $impersonatorId = $claims['impersonator']['id'] ?? null;
            if (\is_string($impersonatorId)) {
                $impersonator = $this->userGateway->get($impersonatorId);
            }
        }

        $this->tenantContext->set($organisation, $user, $impersonator);
        $this->enableTenantFilter($organisation);
    }

    /**
     * Fallback for local dev with Keycloak OIDC tokens.
     * Keycloak includes organization as a list of slugs: ["org-slug"].
     * This will be removed once all environments use the Global Gateway.
     */
    private function handleKeycloakFallback(ControllerEvent $event, User $user): void
    {
        $jwtClaims = $this->extractJwtClaimsFromRequest($event);
        if (null === $jwtClaims) {
            return;
        }

        $normalised = $this->normaliseKeycloakOrgClaims($jwtClaims);
        if (null === $normalised) {
            return;
        }

        $organisation = $this->organisationSyncService->syncFromToken($user, $normalised);
        $this->tenantContext->set($organisation, $user);
        $this->enableTenantFilter($organisation);
    }

    /**
     * Normalises Keycloak's organization claim: ["slug"] → {org_id, org_name}.
     *
     * @param array<string, mixed> $jwtClaims
     *
     * @return array{org_id: string, org_name: string}|null
     */
    private function normaliseKeycloakOrgClaims(array $jwtClaims): ?array
    {
        if (!isset($jwtClaims['organization']) || !\is_array($jwtClaims['organization'])) {
            return null;
        }

        /** @var list<string> $orgs */
        $orgs = $jwtClaims['organization'];
        if ([] === $orgs) {
            return null;
        }

        return [
            'org_id' => $orgs[0],
            'org_name' => $orgs[0],
        ];
    }

    /**
     * Decodes JWT payload from Bearer token for Keycloak fallback.
     *
     * @return array<string, mixed>|null
     */
    private function extractJwtClaimsFromRequest(ControllerEvent $event): ?array
    {
        $authHeader = $event->getRequest()
->headers->get('Authorization');
        if (null === $authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $jwt = substr($authHeader, 7);
        $parts = explode('.', $jwt);
        if (3 !== \count($parts)) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        if (false === $payload) {
            $this->logger->warning('Failed to decode Keycloak JWT payload for tenant context extraction.');

            return null;
        }

        /** @var array<string, mixed>|null $claims */
        $claims = json_decode($payload, true);
        if (!\is_array($claims)) {
            $this->logger->warning('Failed to parse Keycloak JWT payload as JSON.');

            return null;
        }

        return $claims;
    }

    private function enableTenantFilter(Organisation $organisation): void
    {
        $filter = $this->entityManager->getFilters()
->enable('tenant');
        $filter->setParameter('organisation_id', $organisation->getId(), Types::STRING);
    }
}
