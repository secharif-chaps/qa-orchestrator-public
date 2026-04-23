<?php

declare(strict_types=1);

namespace App\Tests\Integration\Organisation;

use App\Application\WatchFile\Actor\AddActorAction;
use App\DataFixtures\Factory\Organisation\OrganisationFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\ActorType;
use App\Domain\Organisation\TenantContext;
use App\Domain\Shared\TranslatedText;
use App\Infrastructure\Organisation\Messenger\TenantStamp;
use App\Tests\Integration\AbstractApiTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Verifies that TenantStamp is attached to messages dispatched via Messenger
 * when TenantContext is initialized, and that the context is restored on receive.
 */
class TenantStampPropagationTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private MessageBusInterface $messageBus;
    private TenantContext $tenantContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->messageBus = self::getContainer()->get(MessageBusInterface::class);
        $this->tenantContext = self::getContainer()->get(TenantContext::class);
    }

    public function testTenantStampIsAttachedWhenContextIsInitialized(): void
    {
        $organisation = OrganisationFactory::createOne();
        $user = UserFactory::createOne();

        $this->tenantContext->set($organisation, $user);

        $watchFile = WatchFileFactory::new()
            ->withOrganisation($organisation)
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $action = new AddActorAction(
            watchFileId: $watchFile->getId(),
            name: 'Test Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test', en: 'Test'),
            primaryDomain: 'test.com',
            score: 0.9,
        );

        $this->messageBus->dispatch($action);

        $envelope = $this->transport('async_priority_high')
->queue()
->first();
        $stamp = $envelope->last(TenantStamp::class);

        $this->assertNotNull($stamp, 'TenantStamp should be attached to the message');
        $this->assertSame($organisation->getId(), $stamp->getOrganisationId());
        $this->assertSame($user->getId(), $stamp->getEffectiveUserId());
        $this->assertNull($stamp->getImpersonatorId());
    }

    public function testTenantStampContainsImpersonatorWhenSet(): void
    {
        $organisation = OrganisationFactory::createOne();
        $user = UserFactory::createOne();
        $impersonator = UserFactory::createOne();

        $this->tenantContext->set($organisation, $user, $impersonator);

        $watchFile = WatchFileFactory::new()
            ->withOrganisation($organisation)
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $action = new AddActorAction(
            watchFileId: $watchFile->getId(),
            name: 'Impersonated Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test', en: 'Test'),
            primaryDomain: 'impersonated.com',
            score: 0.5,
        );

        $this->messageBus->dispatch($action);

        $envelope = $this->transport('async_priority_high')
->queue()
->first();
        $stamp = $envelope->last(TenantStamp::class);

        $this->assertNotNull($stamp);
        $this->assertSame($impersonator->getId(), $stamp->getImpersonatorId());
    }

    public function testNoTenantStampWhenContextNotInitialized(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $action = new AddActorAction(
            watchFileId: $watchFile->getId(),
            name: 'No Context Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test', en: 'Test'),
            primaryDomain: 'nocontext.com',
            score: 0.5,
        );

        $this->messageBus->dispatch($action);

        $envelope = $this->transport('async_priority_high')
->queue()
->first();
        $stamp = $envelope->last(TenantStamp::class);

        $this->assertNull($stamp, 'No TenantStamp should be attached when context is not initialized');
    }

    public function testTenantContextRestoredOnMessageProcessing(): void
    {
        $organisation = OrganisationFactory::createOne();
        $user = UserFactory::createOne();

        $this->tenantContext->set($organisation, $user);

        $watchFile = WatchFileFactory::new()
            ->withOrganisation($organisation)
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $action = new AddActorAction(
            watchFileId: $watchFile->getId(),
            name: 'Restored Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test', en: 'Test'),
            primaryDomain: 'restored.com',
            score: 0.8,
        );

        $this->messageBus->dispatch($action);

        // Process the message — the TenantMiddleware should restore the context
        $this->transport('async_priority_high')
->process(1);

        // After processing, the handler should have created the actor
        // successfully (which requires org context from the WatchFile)
        $this->transport('async_priority_high')
->queue()
->assertEmpty();
    }
}
