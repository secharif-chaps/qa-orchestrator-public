<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile;

use App\Application\WatchFile\UpdateWatchFileReferenceSubjectAction;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Integration tests for UpdateWatchFileReferenceSubjectHandler via MessageBus.
 *
 * Note: UpdateWatchFileReferenceSubjectAction routes to async_priority_low transport,
 * so messages are processed asynchronously (queued).
 *
 * These tests verify the complete flow from N8N workflow:
 * 1. Dispatch action to MessageBus
 * 2. Message is queued in async_priority_low transport
 * 3. Process the queue
 * 4. Database state is correct after processing
 */
class UpdateWatchFileReferenceSubjectHandlerTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getContainer()
            ->get(EntityManagerInterface::class);
    }

    private function refreshWatchFile(string $watchFileId): WatchFile
    {
        $this->entityManager->clear();
        $watchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($watchFile, 'WatchFile should exist');

        return $watchFile;
    }

    public function testUpdateReferenceSubjectSuccess(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $newReferenceSubject = new TranslatedText(
            fr: 'Nouveau sujet de référence pour la veille stratégique.',
            en: 'New reference subject for strategic monitoring.'
        );

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: $newReferenceSubject,
            messageId: null
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(UpdateWatchFileReferenceSubjectAction::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals('New reference subject for strategic monitoring.', $referenceSubject->en);
        $this->assertEquals('Nouveau sujet de référence pour la veille stratégique.', $referenceSubject->fr);
    }

    public function testUpdateReferenceSubjectWithMessageContentId(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $newReferenceSubject = new TranslatedText(
            fr: 'Sujet de référence avec message content ID.',
            en: 'Reference subject with message content ID.'
        );

        // This simulates when the update comes from a chat message
        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: $newReferenceSubject,
            messageId: '550e8400-e29b-41d4-a716-446655440000'
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals('Reference subject with message content ID.', $referenceSubject->en);
    }

    public function testUpdateReferenceSubjectMultipleTimes(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $subjects = [
            new TranslatedText(fr: 'Premier sujet de référence.', en: 'First reference subject.'),
            new TranslatedText(fr: 'Deuxième sujet de référence.', en: 'Second reference subject.'),
            new TranslatedText(
                fr: 'Sujet final de référence stratégique.',
                en: 'Final strategic reference subject.'
            ),
        ];

        foreach ($subjects as $subject) {
            $action = new UpdateWatchFileReferenceSubjectAction(
                watchFileId: $watchFileId,
                referenceSubject: $subject,
                messageId: null
            );
            $this->bus()
                ->dispatch($action);
        }

        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        // Should have the last reference subject after all updates
        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals('Final strategic reference subject.', $referenceSubject->en);
    }

    public function testUpdateReferenceSubjectWithUnicodeContent(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $newReferenceSubject = new TranslatedText(
            fr: 'Sujet de référence avec émojis 🎯 et accents: éàüöñ €£¥',
            en: 'Reference subject with emojis 🎯 and special chars: "quotes" & ampersand €£¥'
        );

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: $newReferenceSubject,
            messageId: null
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertStringContainsString('🎯', $referenceSubject->en);
        $this->assertStringContainsString('€£¥', $referenceSubject->fr);
        $this->assertStringContainsString('éàüöñ', $referenceSubject->fr);
    }

    public function testUpdateReferenceSubjectWithLongContent(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $longTextEN = 'This is a comprehensive reference subject that describes in detail '
            . 'the strategic monitoring context, including market analysis, '
            . 'competitive intelligence, regulatory changes, technological innovations, '
            . 'and business implications across multiple regions and industries. '
            . str_repeat('Additional context paragraph. ', 20);

        $longTextFR = 'Ceci est un sujet de référence complet qui décrit en détail '
            . 'le contexte de veille stratégique, incluant l\'analyse de marché, '
            . 'l\'intelligence concurrentielle, les changements réglementaires, '
            . 'les innovations technologiques et les implications commerciales. '
            . str_repeat('Paragraphe de contexte additionnel. ', 20);

        $newReferenceSubject = new TranslatedText(fr: $longTextFR, en: $longTextEN);

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: $newReferenceSubject,
            messageId: null
        );

        $this->bus()
->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals(trim($longTextEN), $referenceSubject->en);
        $this->assertEquals(trim($longTextFR), $referenceSubject->fr);
    }

    public function testUpdateReferenceSubjectPreservesOtherAttributes(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Test WatchFile Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Store original attributes
        $originalName = $watchFile->getName();
        $originalStatus = $watchFile->getStatus();
        $originalCreatedBy = $watchFile->getCreatedBy();
        $this->assertNotNull($originalCreatedBy);
        $originalCreatedById = $originalCreatedBy->getId();

        $newReferenceSubject = new TranslatedText(
            fr: 'Nouveau sujet de référence.',
            en: 'New reference subject.'
        );

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: $newReferenceSubject,
            messageId: null
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        // Reference subject changed
        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals('New reference subject.', $referenceSubject->en);

        // Other attributes preserved
        $this->assertEquals($originalName, $updatedWatchFile->getName());
        $this->assertEquals($originalStatus, $updatedWatchFile->getStatus());
        $updatedCreatedBy = $updatedWatchFile->getCreatedBy();
        $this->assertNotNull($updatedCreatedBy);
        $this->assertEquals($originalCreatedById, $updatedCreatedBy->getId());
    }

    public function testUpdateReferenceSubjectWithNonExistentIdFails(): void
    {
        $newReferenceSubject = new TranslatedText(fr: 'Sujet de référence.', en: 'Reference subject.');

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: '00000000-0000-0000-0000-000000000000',
            referenceSubject: $newReferenceSubject,
            messageId: null
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertCount(1);

        $this->expectException(\Exception::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
    }

    public function testUpdateReferenceSubjectWithEmptyIdFails(): void
    {
        $newReferenceSubject = new TranslatedText(fr: 'Sujet de référence.', en: 'Reference subject.');

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: '',
            referenceSubject: $newReferenceSubject,
            messageId: null
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertCount(1);

        $this->expectException(\Exception::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
    }

    public function testUpdateReferenceSubjectMessagesAreQueued(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Store original reference subject (might be null)
        $originalReferenceSubject = $watchFile->getReferenceSubject();

        $newReferenceSubject = new TranslatedText(
            fr: 'Sujet de référence en queue.',
            en: 'Queued reference subject.'
        );

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: $newReferenceSubject,
            messageId: null
        );

        $this->bus()
            ->dispatch($action);

        // Before processing, queue should have the action
        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(UpdateWatchFileReferenceSubjectAction::class);

        // Reference subject should not have changed yet (message not processed)
        $this->entityManager->clear();
        $unchangedWatchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($unchangedWatchFile);
        $this->assertEquals($originalReferenceSubject, $unchangedWatchFile->getReferenceSubject());

        // Process the queue
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        // After processing, queue should be empty
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        // Now reference subject should have changed
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals('Queued reference subject.', $referenceSubject->en);
    }

    public function testUpdateReferenceSubjectWithLlmVersion(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $newReferenceSubject = new TranslatedText(
            fr: 'Sujet de référence humain.',
            en: 'Human reference subject.'
        );

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: $newReferenceSubject,
            referenceSubjectLlm: 'LLM optimized: market intelligence competitive analysis Europe AI',
            messageId: null
        );

        $this->bus()
->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        // Verify human version
        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals('Human reference subject.', $referenceSubject->en);
        $this->assertEquals('Sujet de référence humain.', $referenceSubject->fr);

        // Verify LLM version
        $this->assertEquals(
            'LLM optimized: market intelligence competitive analysis Europe AI',
            $updatedWatchFile->getReferenceSubjectLlm()
        );
    }

    public function testUpdateReferenceSubjectWithoutLlmVersionKeepsItNull(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $newReferenceSubject = new TranslatedText(fr: 'Sujet de référence.', en: 'Reference subject.');

        // No LLM version provided
        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: $newReferenceSubject,
            messageId: null
        );

        $this->bus()
->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        // Verify human version is set
        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals('Reference subject.', $referenceSubject->en);

        // Verify LLM version is null (not set)
        $this->assertNull($updatedWatchFile->getReferenceSubjectLlm());
    }

    public function testUpdateReferenceSubjectLlmVersionIsNotExposedInApi(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new UpdateWatchFileReferenceSubjectAction(
            watchFileId: $watchFileId,
            referenceSubject: new TranslatedText(fr: 'Sujet', en: 'Subject'),
            referenceSubjectLlm: 'This should NOT appear in API response',
            messageId: null
        );

        $this->bus()
->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        // Fetch via API
        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files/' . $watchFileId);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();

        // Verify referenceSubject (human version) IS exposed
        $this->assertArrayHasKey('referenceSubject', $data);
        $this->assertEquals('Subject', $data['referenceSubject']['en']);

        // Verify referenceSubjectLlm is NOT exposed in API
        $this->assertArrayNotHasKey('referenceSubjectLlm', $data);
    }
}
