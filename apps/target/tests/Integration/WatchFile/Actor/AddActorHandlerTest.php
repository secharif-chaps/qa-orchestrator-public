<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile\Actor;

use App\Application\WatchFile\Actor\AddActorAction;
use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

class AddActorHandlerTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getContainer()
->get(EntityManagerInterface::class);
        $this->messageBus = $this->getContainer()
->get(MessageBusInterface::class);
    }

    private function refreshWatchFile(string $watchFileId): WatchFile
    {
        $this->entityManager->clear();
        $watchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($watchFile, 'WatchFile should exist');

        return $watchFile;
    }

    public function testDispatchAddActorActionRoutesToAsyncPriorityHigh(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'ACME Corporation',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(
                fr: 'Un concurrent majeur sur le marché.',
                en: 'A major competitor in the market.'
            ),
            primaryDomain: 'acme.com',
            score: 0.85
        );

        $this->messageBus->dispatch($action);

        // Verify message was queued to async_priority_high
        $this->transport('async_priority_high')
->queue()
->assertContains(AddActorAction::class);
        $this->transport('async_priority_high')
->queue()
->assertCount(1);
    }

    public function testProcessAddActorCreatesNewActorAndRelation(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'New Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Nouveau concurrent.', en: 'New competitor.'),
            primaryDomain: 'newcorp.com',
            score: 0.90
        );

        $this->messageBus->dispatch($action);

        // Process the queued message
        $this->transport('async_priority_high')
->process(1);
        $this->transport('async_priority_high')
->queue()
->assertEmpty();

        // Verify database state
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();

        $this->assertCount(1, $watchFileActors);
        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        $this->assertEquals('New Corp', $watchFileActor->getActor()->getLabel());
        $this->assertEquals(ActorType::COMPETITOR, $watchFileActor->getType());
        $this->assertEquals(0.90, $watchFileActor->getScore());
        $explanations = $watchFileActor->getExplanations();
        $this->assertNotNull($explanations);
        $this->assertEquals('New competitor.', $explanations->en);
    }

    public function testProcessAddActorReusesExistingActor(): void
    {
        $user = UserFactory::createOne();
        $existingActor = ActorFactory::createOne([
            'label' => 'Existing Corp',
            'primaryDomain' => 'existing.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();
        $existingActorId = $existingActor->getId();

        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Existing Corp', // Same label as existing
            type: ActorType::PARTNER,
            explanation: new TranslatedText(fr: 'Partenaire.', en: 'Partner.'),
            primaryDomain: 'existing.com',
            score: 0.75
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        // Verify existing actor was reused
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        $this->assertEquals($existingActorId, $watchFileActor->getActor()->getId());
    }

    public function testProcessAddActorDeduplicatesByPrimaryDomain(): void
    {
        $user = UserFactory::createOne();
        $existingActor = ActorFactory::createOne([
            'label' => 'Original Company Name',
            'primaryDomain' => 'company.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();
        $existingActorId = $existingActor->getId();

        // Try to add actor with DIFFERENT label but SAME primary domain
        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Different Company Name', // Different label
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test déduplication.', en: 'Deduplication test.'),
            primaryDomain: 'https://company.com/about', // Same domain (full URL)
            score: 0.85
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        // Verify existing actor was reused (deduplicated by domain)
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        // Should be the existing actor, not a new one
        $this->assertEquals($existingActorId, $watchFileActor->getActor()->getId());
        $this->assertEquals('Original Company Name', $watchFileActor->getActor()->getLabel());
    }

    public function testProcessAddActorLabelMatchTakesPrecedenceOverDomainMatch(): void
    {
        $user = UserFactory::createOne();

        // Create actor with label "Target Corp" and domain "other.com"
        $actorByLabel = ActorFactory::createOne([
            'label' => 'Target Corp',
            'primaryDomain' => 'other.com',
        ]);

        // Create actor with label "Different Corp" and domain "different.com"
        ActorFactory::createOne([
            'label' => 'Different Corp',
            'primaryDomain' => 'different.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();
        $actorByLabelId = $actorByLabel->getId();

        // Add actor with matching label and a new domain (not conflicting with existing)
        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Target Corp', // Matches first actor by label
            type: ActorType::PARTNER,
            explanation: new TranslatedText(fr: 'Test priorité.', en: 'Priority test.'),
            primaryDomain: 'new-domain.com', // New domain, no conflict
            score: 0.75
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        // Should use the actor matched by label (first actor)
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        $this->assertEquals($actorByLabelId, $watchFileActor->getActor()->getId());
        $this->assertEquals('Target Corp', $watchFileActor->getActor()->getLabel());
        // Domain should be updated to the new one
        $this->assertEquals('new-domain.com', $watchFileActor->getActor()->getPrimaryDomain());
    }

    public function testProcessAddActorNoDuplicateWhenDifferentDomain(): void
    {
        $user = UserFactory::createOne();
        $existingActor = ActorFactory::createOne([
            'label' => 'Existing Corp',
            'primaryDomain' => 'existing.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();
        $existingActorId = $existingActor->getId();

        // Add actor with different label AND different domain
        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'New Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Nouvel acteur.', en: 'New actor.'),
            primaryDomain: 'newcorp.com',
            score: 0.80
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        // Should create a new actor (no deduplication)
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        // Should NOT be the existing actor
        $this->assertNotEquals($existingActorId, $watchFileActor->getActor()->getId());
        $this->assertEquals('New Corp', $watchFileActor->getActor()->getLabel());
        $this->assertEquals('newcorp.com', $watchFileActor->getActor()->getPrimaryDomain());
    }

    public function testProcessAddActorDeduplicationPreventsDuplicateConstraintViolation(): void
    {
        $user = UserFactory::createOne();

        // Create an existing actor with a domain
        $existingActor = ActorFactory::createOne([
            'label' => 'First Name',
            'primaryDomain' => 'unique-domain.com',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();
        $existingActorId = $existingActor->getId();

        // Try to add actor with different name but same domain
        // This would cause a unique constraint violation if not properly deduplicated
        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Second Name',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test.', en: 'Test.'),
            primaryDomain: 'unique-domain.com', // Same domain - should deduplicate
            score: 0.90
        );

        $this->messageBus->dispatch($action);

        // Should NOT throw an exception - should deduplicate instead
        $this->transport('async_priority_high')
->process(1);

        // Verify existing actor was reused
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        $this->assertEquals($existingActorId, $watchFileActor->getActor()->getId());
    }

    public function testProcessDuplicateActorWithSameTypeIsIgnored(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Dispatch two identical actions
        $action1 = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Duplicate Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Premier.', en: 'First.'),
            primaryDomain: 'duplicate.com',
            score: 0.80
        );

        $action2 = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Duplicate Corp',
            type: ActorType::COMPETITOR, // Same type
            explanation: new TranslatedText(fr: 'Deuxième.', en: 'Second.'),
            primaryDomain: 'duplicate.com',
            score: 0.95
        );

        $this->messageBus->dispatch($action1);
        $this->messageBus->dispatch($action2);

        // Process both
        $this->transport('async_priority_high')
->process();
        $this->transport('async_priority_high')
->queue()
->assertEmpty();

        // Should only have one relation
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        // First action values should be preserved
        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        $this->assertEquals(0.80, $watchFileActor->getScore());
    }

    /**
     * Business rule: an actor can only be linked once to a watchfile, regardless of type.
     * Adding the same actor with a different type should be ignored.
     */
    public function testProcessSameActorWithDifferentTypesKeepsOnlyFirstRelation(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Same actor, different types
        $action1 = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Multi-Role Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Concurrent.', en: 'Competitor.'),
            primaryDomain: 'multirole.com',
            score: 0.80
        );

        $action2 = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Multi-Role Corp',
            type: ActorType::PARTNER, // Different type - should be ignored
            explanation: new TranslatedText(fr: 'Partenaire.', en: 'Partner.'),
            primaryDomain: 'multirole.com',
            score: 0.70
        );

        $this->messageBus->dispatch($action1);
        $this->messageBus->dispatch($action2);

        $this->transport('async_priority_high')
->process();

        // Should have only one relation (second action ignored because actor already linked)
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        // Verify it's the first type that was kept
        $firstWatchFileActor = $watchFileActors->first();
        $this->assertNotFalse($firstWatchFileActor);
        $this->assertSame(ActorType::COMPETITOR, $firstWatchFileActor->getType());
        $this->assertSame(0.80, $firstWatchFileActor->getScore());
    }

    public function testProcessMultipleActorsInBatch(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Dispatch batch of actors (simulating N8N workflow output)
        $actors = [
            [
                'name' => 'Google',
                'type' => ActorType::COMPETITOR,
                'score' => 0.95,
            ],
            [
                'name' => 'Microsoft',
                'type' => ActorType::COMPETITOR,
                'score' => 0.90,
            ],
            [
                'name' => 'Amazon',
                'type' => ActorType::SUPPLIER,
                'score' => 0.85,
            ],
        ];

        foreach ($actors as $actorData) {
            $action = new AddActorAction(
                watchFileId: $watchFileId,
                name: $actorData['name'],
                type: $actorData['type'],
                explanation: new TranslatedText(
                    fr: "Description de {$actorData['name']}",
                    en: "Description of {$actorData['name']}"
                ),
                primaryDomain: strtolower($actorData['name']) . '.com',
                score: $actorData['score']
            );
            $this->messageBus->dispatch($action);
        }

        // Verify all queued
        $this->transport('async_priority_high')
->queue()
->assertCount(3);

        // Process all
        $this->transport('async_priority_high')
->process();
        $this->transport('async_priority_high')
->queue()
->assertEmpty();

        // Verify all created
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(3, $watchFileActors);

        $actorNames = array_map(fn ($wfa) => $wfa->getActor()->getLabel(), $watchFileActors->toArray());
        $this->assertContains('Google', $actorNames);
        $this->assertContains('Microsoft', $actorNames);
        $this->assertContains('Amazon', $actorNames);
    }

    public function testProcessActorWithNullScore(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Null Score Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Sans score.', en: 'No score.'),
            primaryDomain: 'nullscore.com',
            score: null // LLM might not provide score
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        // Default score should be 0.0
        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        $this->assertEquals(0.0, $watchFileActor->getScore());
    }

    public function testProcessActorWithNullPrimaryDomain(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'No Domain Corp',
            type: ActorType::OTHER,
            explanation: new TranslatedText(fr: 'Sans domaine.', en: 'No domain.'),
            primaryDomain: null, // Person or unknown entity
            score: 0.70
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);
    }

    public function testProcessActorWithUnicodeCharacters(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM may output Unicode, accents, special chars
        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Société Générale — Banque Française',
            type: ActorType::PARTNER,
            explanation: new TranslatedText(
                fr: 'Partenaire bancaire «stratégique».',
                en: 'Strategic banking "partner".'
            ),
            primaryDomain: 'societegenerale.fr',
            score: 0.88
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        $actor = $watchFileActor->getActor();
        $this->assertStringContainsString('Société Générale', $actor->getLabel());
    }

    public function testProcessFailsForNonExistentWatchFile(): void
    {
        $action = new AddActorAction(
            watchFileId: '00000000-0000-0000-0000-000000000000',
            name: 'Orphan Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test.', en: 'Test.'),
            primaryDomain: 'orphan.com',
            score: 0.50
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->queue()
->assertCount(1);

        // Processing should fail - use throwExceptions() to catch handler exceptions
        $this->expectException(\Exception::class);
        $this->transport('async_priority_high')
->throwExceptions()
->process(1);
    }

    public function testProcessActorWithFullUrlExtractsDomainCorrectly(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM often provides full URLs instead of just domains
        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Full URL Corp',
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test URL complète.', en: 'Full URL test.'),
            primaryDomain: 'https://www.example.com/about/company?ref=123',
            score: 0.75
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        // Should have extracted just the domain
        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor);
        $actor = $watchFileActor->getActor();
        $this->assertEquals('www.example.com', $actor->getPrimaryDomain());
    }

    public function testProcessActorWithDomainAndPathExtractsDomainCorrectly(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Domain with path but no protocol
        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Path Only Corp',
            type: ActorType::PARTNER,
            explanation: new TranslatedText(fr: 'Domaine avec path.', en: 'Domain with path.'),
            primaryDomain: 'example.org/products/item',
            score: 0.80
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process(1);

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActor = $updatedWatchFile->getWatchFileActors()
->first();
        $this->assertNotFalse($watchFileActor);
        $actor = $watchFileActor->getActor();
        $this->assertEquals('example.org', $actor->getPrimaryDomain());
    }

    /**
     * @return iterable<string, array{input: string, expectedDomain: string|null}>
     */
    public static function domainExtractionProvider(): iterable
    {
        // Valid URLs that should extract domain correctly
        yield 'full https URL' => [
            'input' => 'https://www.example.com/about/company?ref=123',
            'expectedDomain' => 'www.example.com',
        ];
        yield 'full http URL' => [
            'input' => 'http://example.org/page',
            'expectedDomain' => 'example.org',
        ];
        yield 'domain with path no protocol' => [
            'input' => 'example.org/products/item',
            'expectedDomain' => 'example.org',
        ];
        yield 'plain domain' => [
            'input' => 'example.com',
            'expectedDomain' => 'example.com',
        ];
        yield 'subdomain' => [
            'input' => 'api.example.com',
            'expectedDomain' => 'api.example.com',
        ];
        yield 'malformed protocol htp' => [
            'input' => 'htp://example.com/page',
            'expectedDomain' => 'example.com',
        ];
        yield 'ftp protocol' => [
            'input' => 'ftp://files.example.com',
            'expectedDomain' => 'files.example.com',
        ];
        yield 'domain with port' => [
            'input' => 'https://example.com:8080/api',
            'expectedDomain' => 'example.com',
        ];
        yield 'international domain' => [
            'input' => 'https://xn--n3h.com',
            'expectedDomain' => 'xn--n3h.com',
        ];
        yield 'domain with hyphen' => [
            'input' => 'my-company.example.com',
            'expectedDomain' => 'my-company.example.com',
        ];
        yield '.me TLD' => [
            'input' => 'about.me',
            'expectedDomain' => 'about.me',
        ];
        yield '.xyz TLD' => [
            'input' => 'https://abc.xyz/investor',
            'expectedDomain' => 'abc.xyz',
        ];
        yield '.io TLD' => [
            'input' => 'github.io',
            'expectedDomain' => 'github.io',
        ];
        yield '.co TLD' => [
            'input' => 'twitter.co',
            'expectedDomain' => 'twitter.co',
        ];
        yield 'e.leclerc (French retailer)' => [
            'input' => 'https://www.e.leclerc/catalogue',
            'expectedDomain' => 'www.e.leclerc',
        ];
        yield '.museum long TLD' => [
            'input' => 'https://www.louvre.museum',
            'expectedDomain' => 'www.louvre.museum',
        ];
        yield '.technology long TLD' => [
            'input' => 'startup.technology',
            'expectedDomain' => 'startup.technology',
        ];
        yield '.photography long TLD' => [
            'input' => 'https://portfolio.photography/gallery',
            'expectedDomain' => 'portfolio.photography',
        ];
        yield '.gouv.fr (French government)' => [
            'input' => 'https://www.service-public.gouv.fr',
            'expectedDomain' => 'www.service-public.gouv.fr',
        ];
        yield '.co.uk (UK domain)' => [
            'input' => 'https://www.bbc.co.uk/news',
            'expectedDomain' => 'www.bbc.co.uk',
        ];
        yield '.com.br (Brazilian domain)' => [
            'input' => 'empresa.com.br',
            'expectedDomain' => 'empresa.com.br',
        ];
        yield 'single letter subdomain' => [
            'input' => 'https://t.co/shortlink',
            'expectedDomain' => 't.co',
        ];

        // Invalid inputs that should return null
        yield 'empty string' => [
            'input' => '',
            'expectedDomain' => null,
        ];
        yield 'whitespace only' => [
            'input' => '   ',
            'expectedDomain' => null,
        ];
        yield 'garbage text' => [
            'input' => 'not a valid url at all',
            'expectedDomain' => null,
        ];
        yield 'no TLD (localhost)' => [
            'input' => 'localhost',
            'expectedDomain' => null,
        ];
        yield 'just numbers' => [
            'input' => '12345',
            'expectedDomain' => null,
        ];
        yield 'special characters' => [
            'input' => '@#$%.com',
            'expectedDomain' => null,
        ];
        yield 'emoji domain' => [
            'input' => '🎯.com',
            'expectedDomain' => null,
        ];
        yield 'spaces in domain' => [
            'input' => 'example .com',
            'expectedDomain' => null,
        ];
        yield 'underscore in domain' => [
            'input' => 'my_domain.com',
            'expectedDomain' => null,
        ];
        yield 'LLM hallucinated text' => [
            'input' => 'The company website is example.com',
            'expectedDomain' => null,
        ];
        yield 'starts with hyphen' => [
            'input' => '-invalid.com',
            'expectedDomain' => null,
        ];
        yield 'ends with hyphen' => [
            'input' => 'invalid-.com',
            'expectedDomain' => null,
        ];
        yield 'double dots' => [
            'input' => 'example..com',
            'expectedDomain' => null,
        ];
    }

    #[DataProvider('domainExtractionProvider')]
    public function testDomainExtraction(string $input, ?string $expectedDomain): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new AddActorAction(
            watchFileId: $watchFileId,
            name: 'Test Corp - ' . substr($input, 0, 20),
            type: ActorType::COMPETITOR,
            explanation: new TranslatedText(fr: 'Test extraction.', en: 'Extraction test.'),
            primaryDomain: $input,
            score: 0.75
        );

        $this->messageBus->dispatch($action);
        $this->transport('async_priority_high')
->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActor = $updatedWatchFile->getWatchFileActors()
->first();
        $this->assertNotFalse($watchFileActor);
        $actor = $watchFileActor->getActor();

        $this->assertSame(
            $expectedDomain,
            $actor->getPrimaryDomain(),
            \sprintf('Input "%s" should extract to "%s"', $input, $expectedDomain ?? 'null')
        );
    }
}
