<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Integration\AbstractApiTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Integration tests for WatchFile activation validation.
 *
 * These tests verify that a WatchFile cannot be activated (set to ENABLED status)
 * unless it has:
 * - At least one active source
 * - A reference subject (non-empty)
 */
class WatchFileActivationValidationTest extends AbstractApiTestCase
{
    private function getTranslatedMessage(string $key, string $locale = 'en'): string
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);

        return $translator->trans($key, [], 'messages', $locale);
    }

    public function testCannotActivateWatchFileWithoutActiveSource(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File Without Sources',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'Error',
        ]);
    }

    public function testCannotActivateWatchFileWithOnlyInactiveSources(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File With Inactive Sources',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create only inactive sources
        SourceFactory::new()
            ->with([
                'name' => 'Inactive Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://inactive-example.com',
                'primaryDomain' => 'inactive-example.com',
                'status' => SourceStatus::INACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'Error',
        ]);
    }

    public function testCannotActivateWatchFileWithoutReferenceSubject(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File Without Reference Subject',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => null,
            ])
            ->create();

        // Create an active source
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(422);
        $this->assertJsonContains([
            '@type' => 'Error',
        ]);
    }

    public function testCanActivateWatchFileWithActiveSourceAndReferenceSubject(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Valid Watch File',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create an active source
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testCanActivateWatchFileWithMixedSourcesAndReferenceSubject(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File With Mixed Sources',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create one active and one inactive source
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://active.com',
                'primaryDomain' => 'active.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        SourceFactory::new()
            ->with([
                'name' => 'Inactive Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://inactive.com',
                'primaryDomain' => 'inactive.com',
                'status' => SourceStatus::INACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testCanDeactivateWatchFileWithoutActiveSourceOrReferenceSubject(): void
    {
        $user = UserFactory::createOne();

        // Create an ENABLED watch file (simulating corrupted/legacy state)
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Enabled Watch File Without Requirements',
                'status' => WatchFileStatus::ENABLED,
                'referenceSubject' => null,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Should be able to deactivate (change to DRAFT)
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/draft");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('draft', $data['status']);
    }

    public function testCanArchiveWatchFileWithoutActiveSourceOrReferenceSubject(): void
    {
        $user = UserFactory::createOne();

        // Create an ENABLED watch file (simulating corrupted/legacy state)
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Enabled Watch File Without Requirements',
                'status' => WatchFileStatus::ENABLED,
                'referenceSubject' => null,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Should be able to archive
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/archived");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('archived', $data['status']);
    }

    public function testIdempotentEnableWhenAlreadyEnabledWithoutRequirements(): void
    {
        $user = UserFactory::createOne();

        // Create an ENABLED watch file (simulating corrupted/legacy state)
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Already Enabled Watch File',
                'status' => WatchFileStatus::ENABLED,
                'referenceSubject' => null,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Should succeed (idempotent - already enabled, no status change needed)
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseStatusCodeSame(201);
        $data = json_decode($response->getContent(), true);
        $this->assertIsArray($data);
        $this->assertEquals('enabled', $data['status']);
    }

    public function testMissingActiveSourceErrorMessageInEnglish(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File Without Sources',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled", [
            'headers' => [
                'Accept-Language' => 'en',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $expectedMessage = $this->getTranslatedMessage('watchfile.activation.missing_active_source', 'en');
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testMissingActiveSourceErrorMessageInFrench(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File Without Sources',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled", [
            'headers' => [
                'Accept-Language' => 'fr',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $expectedMessage = $this->getTranslatedMessage('watchfile.activation.missing_active_source', 'fr');
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testMissingReferenceSubjectErrorMessageInEnglish(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File Without Reference Subject',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => null,
            ])
            ->create();

        // Create an active source
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled", [
            'headers' => [
                'Accept-Language' => 'en',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $expectedMessage = $this->getTranslatedMessage('watchfile.activation.missing_reference_subject', 'en');
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }

    public function testMissingReferenceSubjectErrorMessageInFrench(): void
    {
        $user = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Watch File Without Reference Subject',
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => null,
            ])
            ->create();

        // Create an active source
        SourceFactory::new()
            ->with([
                'name' => 'Active Source',
                'type' => SourceType::WEBSITE,
                'url' => 'https://example.com',
                'primaryDomain' => 'example.com',
                'status' => SourceStatus::ACTIVE,
                'watchFile' => $watchFile,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled", [
            'headers' => [
                'Accept-Language' => 'fr',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $expectedMessage = $this->getTranslatedMessage('watchfile.activation.missing_reference_subject', 'fr');
        $this->assertJsonContains([
            '@type' => 'Error',
            'detail' => $expectedMessage,
        ]);
    }
}
