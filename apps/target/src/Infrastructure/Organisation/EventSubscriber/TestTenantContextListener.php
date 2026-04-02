<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation\EventSubscriber;

use App\Application\Organisation\OrganisationSyncService;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Test-only listener that reads X-Test-Auth-Org-Id header to set up tenant context.
 * Runs at lower priority than TenantContextListener so it only activates when
 * the production listener did not set context (i.e. test environment with TestAuthenticator).
 */
#[AsEventListener(event: KernelEvents::CONTROLLER, priority: 8)]
class TestTenantContextListener
{
    public const string HEADER_TEST_AUTH_ORG_ID = 'X-Test-Auth-Org-Id';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly OrganisationSyncService $organisationSyncService,
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $environment,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if ('test' !== $this->environment) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $orgKeycloakId = $event->getRequest()
->headers->get(self::HEADER_TEST_AUTH_ORG_ID);
        if (null === $orgKeycloakId || '' === $orgKeycloakId) {
            return;
        }

        // Skip if TenantContextListener already set context
        if ($this->tenantContext->isInitialized()) {
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

        $normalised = [
            'org_id' => $orgKeycloakId,
            'org_name' => $orgKeycloakId,
        ];
        $organisation = $this->organisationSyncService->syncFromToken($user, $normalised);

        $this->tenantContext->set($organisation, $user);

        $filter = $this->entityManager->getFilters()
->enable('tenant');
        $filter->setParameter('organisation_id', $organisation->getId(), Types::STRING);
    }
}
