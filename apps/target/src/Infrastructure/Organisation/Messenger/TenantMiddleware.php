<?php

declare(strict_types=1);

namespace App\Infrastructure\Organisation\Messenger;

use App\Application\Agent\TriggerAgent;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Messenger middleware that propagates tenant context across async boundaries.
 *
 * - On SEND: attaches a TenantStamp with current org/user/impersonator IDs.
 * - On RECEIVE: restores TenantContext and enables Doctrine TenantFilter from the stamp.
 */
class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserGatewayInterface $userGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // RECEIVE side: restore context from stamp
        if (null !== $envelope->last(ReceivedStamp::class)) {
            $envelope = $this->restoreContext($envelope);

            return $stack->next()
->handle($envelope, $stack);
        }

        // SEND side: attach stamp from current context
        if (null === $envelope->last(TenantStamp::class) && $this->tenantContext->isInitialized()) {
            $organisationId = $this->tenantContext->getCurrentOrganisation()
->getId() ?? '';
            $effectiveUserId = $this->tenantContext->getEffectiveUser()
->getId() ?? '';
            $impersonatorId = $this->tenantContext->getImpersonator()?->getId();

            $envelope = $envelope->with(new TenantStamp(
                organisationId: $organisationId,
                effectiveUserId: $effectiveUserId,
                impersonatorId: $impersonatorId,
            ));

            // Enrich TriggerAgent messages with tenant IDs for N8N forwarding
            $message = $envelope->getMessage();
            if ($message instanceof TriggerAgent) {
                $message->organisationId = $organisationId;
                $message->impersonatorId = $impersonatorId;
            }
        }

        return $stack->next()
->handle($envelope, $stack);
    }

    private function restoreContext(Envelope $envelope): Envelope
    {
        $stamp = $envelope->last(TenantStamp::class);
        if (!$stamp instanceof TenantStamp) {
            return $envelope;
        }

        $organisation = $this->entityManager->find(Organisation::class, $stamp->getOrganisationId());
        if (null === $organisation) {
            $this->logger?->warning('TenantMiddleware: organisation not found, skipping context restore', [
                'organisation_id' => $stamp->getOrganisationId(),
            ]);

            return $envelope;
        }

        try {
            $effectiveUser = $this->userGateway->get($stamp->getEffectiveUserId());
        } catch (UserNotFoundException $e) {
            $this->logger?->warning('TenantMiddleware: user not found, skipping context restore', [
                'user_id' => $stamp->getEffectiveUserId(),
                'error' => $e->getMessage(),
            ]);

            return $envelope;
        }

        $impersonator = null;
        if (null !== $stamp->getImpersonatorId()) {
            try {
                $impersonator = $this->userGateway->get($stamp->getImpersonatorId());
            } catch (UserNotFoundException) {
                $this->logger?->warning('TenantMiddleware: impersonator not found, proceeding without impersonation', [
                    'impersonator_id' => $stamp->getImpersonatorId(),
                ]);
            }
        }

        $this->tenantContext->set($organisation, $effectiveUser, $impersonator);

        // Enable Doctrine tenant filter
        $filter = $this->entityManager->getFilters()
->enable('tenant');
        $filter->setParameter('organisation_id', $organisation->getId(), Types::STRING);

        $this->logger?->debug('TenantMiddleware: context restored from stamp', [
            'organisation_id' => $organisation->getId(),
            'user_id' => $effectiveUser->getId(),
        ]);

        return $envelope;
    }
}
