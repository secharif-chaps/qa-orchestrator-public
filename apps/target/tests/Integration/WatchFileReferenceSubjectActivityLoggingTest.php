<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Tests\Units\Infrastructure\WatchFileActivity\NullWatchFileActivityGateway;

/**
 * Integration tests for WatchFile referenceSubject activity logging.
 * Tests both manual updates via API and AI detection scenarios.
 */
class WatchFileReferenceSubjectActivityLoggingTest extends AbstractApiTestCase
{
    private NullWatchFileActivityGateway $activityGateway;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityGateway = new NullWatchFileActivityGateway();
        self::getContainer()->set(
            'App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface',
            $this->activityGateway
        );
        $this->client = $this->createAuthenticatedClient();
    }

    /**
     * Test that manual referenceSubject update via API logs UPDATED activity.
     */
    public function testManualReferenceSubjectUpdateLogsActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => \App\Domain\Shared\TranslatedText::fromArray([
                    'fr' => 'Ancien sujet de référence',
                    'en' => 'Old reference subject',
                ]),
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        $response = $this->client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'referenceSubject' => [
                    'fr' => 'Nouveau sujet de référence depuis l\'API',
                    'en' => 'New reference subject from API',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Verify activity was logged
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertCount(1, $activities);

        $activity = iterator_to_array($activities)[0];
        $this->assertEquals(WatchFileActivityActionType::UPDATED, $activity->getActionType());
        $this->assertEquals($user->getId(), $activity->getUser()->getId());

        $actionData = $activity->getActionData();
        $this->assertIsArray($actionData['changes']);
        $this->assertArrayHasKey('referenceSubject', $actionData['changes']);

        $referenceSubjectChanges = $actionData['changes']['referenceSubject'];
        $this->assertIsArray($referenceSubjectChanges);
        $this->assertIsArray($referenceSubjectChanges['old']);
        $this->assertIsArray($referenceSubjectChanges['new']);
        $this->assertEquals('Old reference subject', $referenceSubjectChanges['old']['en']);
        $this->assertEquals('New reference subject from API', $referenceSubjectChanges['new']['en']);
    }

    /**
     * Test that updating referenceSubject from null to a value logs correctly.
     */
    public function testReferenceSubjectUpdateFromNullLogsActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => null,
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        $response = $this->client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'referenceSubject' => [
                    'fr' => 'Premier sujet de référence',
                    'en' => 'First reference subject',
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Verify activity was logged
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertCount(1, $activities);

        $activity = iterator_to_array($activities)[0];
        $this->assertEquals(WatchFileActivityActionType::UPDATED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertIsArray($actionData['changes']);
        $this->assertArrayHasKey('referenceSubject', $actionData['changes']);

        $referenceSubjectChanges = $actionData['changes']['referenceSubject'];
        $this->assertIsArray($referenceSubjectChanges);
        $this->assertNull($referenceSubjectChanges['old']);
        $this->assertIsArray($referenceSubjectChanges['new']);
        $this->assertEquals('First reference subject', $referenceSubjectChanges['new']['en']);
    }

    /**
     * Test that clearing referenceSubject logs correctly.
     */
    public function testReferenceSubjectClearingLogsActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => \App\Domain\Shared\TranslatedText::fromArray([
                    'fr' => 'Sujet de référence existant',
                    'en' => 'Existing reference subject',
                ]),
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        $response = $this->client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'referenceSubject' => null,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Verify activity was logged
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertCount(1, $activities);

        $activity = iterator_to_array($activities)[0];
        $this->assertEquals(WatchFileActivityActionType::UPDATED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertIsArray($actionData['changes']);
        $this->assertArrayHasKey('referenceSubject', $actionData['changes']);

        $referenceSubjectChanges = $actionData['changes']['referenceSubject'];
        $this->assertIsArray($referenceSubjectChanges);
        $this->assertIsArray($referenceSubjectChanges['old']);
        $this->assertEquals('Existing reference subject', $referenceSubjectChanges['old']['en']);
        $this->assertNull($referenceSubjectChanges['new']);
    }
}
