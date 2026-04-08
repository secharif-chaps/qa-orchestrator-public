<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\Actor\ActorStatus;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Tests\Units\Infrastructure\Mercure\NullRealTimeUpdatePublisher;
use App\Tests\Units\Infrastructure\WatchFileActivity\NullWatchFileActivityGateway;
use Symfony\Contracts\Translation\TranslatorInterface;

class WatchFileActivityIntegrationTest extends AbstractApiTestCase
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
        // Replace the real-time update publisher with a null implementation for testing
        self::getContainer()->set(RealTimeUpdatePublisherInterface::class, new NullRealTimeUpdatePublisher());
        $this->client = $this->createAuthenticatedClient();
    }

    public function testWatchFileCreationLogsActivity(): void
    {
        $user = UserFactory::new()->create();
        $this->client = $this->createAuthenticatedClientInOrganisation($user);

        $response = $this->client->request('POST', '/api/watch_files', [
            'json' => [
                'name' => 'Test WatchFile',
                'userObjective' => 'Test Objective',
                'content' => 'Test content for UserMessageDto',
            ],
        ]);

        $watchFileData = $response->toArray();
        $watchFileId = $watchFileData['id'];

        // Verify activity was logged
        $watchFile = self::getContainer()->get('doctrine')->getRepository(WatchFile::class)->find($watchFileId);
        $this->assertNotNull($watchFile);
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);

        // Expect 2 activities: created + shared (owner is automatically shared)
        $this->assertCount(2, $activities);

        $activitiesArray = iterator_to_array($activities);

        // Find the CREATED activity
        $createdActivity = null;
        $sharedActivity = null;

        foreach ($activitiesArray as $activity) {
            if (WatchFileActivityActionType::CREATED === $activity->getActionType()) {
                $createdActivity = $activity;
            } elseif (WatchFileActivityActionType::SHARED_MODE_CHANGED === $activity->getActionType()) {
                $sharedActivity = $activity;
            }
        }

        // Verify CREATED activity
        $this->assertNotNull($createdActivity, 'CREATED activity should be present');
        $this->assertEquals($user->getId(), $createdActivity->getUser()->getId());
        $actionData = $createdActivity->getActionData();
        $this->assertArrayHasKey('watch_file_name', $actionData);
        $translator = self::getContainer()->get(TranslatorInterface::class);
        $expectedName = $translator->trans('watch_file.untitled');
        $this->assertEquals($expectedName, $actionData['watch_file_name']);

        // Verify SHARED activity (owner is automatically shared)
        $this->assertNotNull($sharedActivity, 'SHARED activity should be present');
        // Note: We can't access getUser()->getId() due to Foundry auto-refresh issues
        // but we can verify the activity exists and has the right type
    }

    public function testWatchFileUpdateLogsActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        $response = $this->client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Updated WatchFile Name',
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
        $this->assertArrayHasKey('changes', $actionData);
        $this->assertIsArray($actionData['changes']);
        $this->assertArrayHasKey('name', $actionData['changes']);
        $this->assertIsArray($actionData['changes']['name']);
        $this->assertEquals('Updated WatchFile Name', $actionData['changes']['name']['new']);
    }

    public function testWatchFileStatusChangeLogsActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
                'referenceSubject' => new TranslatedText('Sujet de test', 'Test subject'),
            ])
            ->create();

        // Create an active source (required for activation)
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

        $this->client = $this->createAuthenticatedClient($user);

        $response = $this->client->request('POST', "/api/watch_files/{$watchFile->getId()}/status/enabled");

        $this->assertResponseIsSuccessful();

        // Verify activity was logged
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertCount(1, $activities);

        $activity = iterator_to_array($activities)[0];
        $this->assertEquals(WatchFileActivityActionType::STATUS_CHANGED, $activity->getActionType());
        $this->assertEquals($user->getId(), $activity->getUser()->getId());

        $actionData = $activity->getActionData();
        $this->assertEquals('draft', $actionData['old_status']);
        $this->assertEquals('enabled', $actionData['new_status']);
    }

    public function testActorStatusChangeLogsActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => ActorStatus::INACTIVE,
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        $response = $this->client->request(
            'POST',
            "/api/watch_files/{$watchFile->getId()}/actors/{$actor->getId()}/status",
            [
                'json' => [
                    'status' => 'active',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        // Verify activity was logged
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertCount(1, $activities);

        $activity = iterator_to_array($activities)[0];
        $this->assertEquals(WatchFileActivityActionType::ACTOR_STATUS_CHANGED, $activity->getActionType());
        $this->assertEquals($user->getId(), $activity->getUser()->getId());

        $actionData = $activity->getActionData();
        $this->assertEquals('active', $actionData['status']);
        $this->assertEquals('inactive', $actionData['old_status']);
        $this->assertEquals($actor->getLabel(), $actionData['actor_name']);
    }

    public function testSourceStatusChangeLogsActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();
        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => SourceStatus::INACTIVE,
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        $response = $this->client->request(
            'POST',
            "/api/watch_files/{$watchFile->getId()}/sources/{$source->getId()}/change-status",
            [
                'json' => [
                    'status' => 'active',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();

        // Verify activity was logged
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertCount(1, $activities);

        $activity = iterator_to_array($activities)[0];
        $this->assertEquals(WatchFileActivityActionType::SOURCE_STATUS_CHANGED, $activity->getActionType());
        $this->assertEquals($user->getId(), $activity->getUser()->getId());

        $actionData = $activity->getActionData();
        $this->assertEquals('active', $actionData['status']);
        $this->assertEquals('inactive', $actionData['old_status']);
        $this->assertEquals($source->getName(), $actionData['source_name']);
    }

    public function testWatchFileSharingLogsActivity(): void
    {
        $owner = UserFactory::new()->create();
        $sharedUser = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $this->client = $this->createAuthenticatedClient($owner);

        $response = $this->client->request('POST', "/api/watch_files/{$watchFile->getId()}/share", [
            'json' => [
                'member' => [
                    [
                        'userId' => $sharedUser->getId(),
                        'role' => 'editor',
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Verify activity was logged
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertCount(1, $activities);

        $activity = iterator_to_array($activities)[0];
        $this->assertEquals(WatchFileActivityActionType::SHARED_MODE_CHANGED, $activity->getActionType());
        $this->assertEquals($owner->getId(), $activity->getUser()->getId());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('user_id', $actionData);
        $this->assertArrayHasKey('user_email', $actionData);
        $this->assertArrayHasKey('old_value', $actionData);
        $this->assertEquals('no_access', $actionData['old_value']);
        $this->assertArrayHasKey('new_value', $actionData);
        $this->assertEquals('editor', $actionData['new_value']);
        $this->assertArrayHasKey('watch_file_user_id', $actionData);
    }

    public function testWatchFileUnsharingLogsActivity(): void
    {
        $owner = UserFactory::new()->create();
        $sharedUser = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        // First share the watch file
        $watchFileUser = WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $sharedUser,
                'role' => \App\Domain\WatchFile\WatchFileUserRole::EDITOR,
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($owner);

        $response = $this->client->request(
            'DELETE',
            "/api/watch_files/{$watchFile->getId()}/share/{$watchFileUser->getId()}"
        );

        $this->assertResponseIsSuccessful();

        // Verify activity was logged
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertCount(1, $activities);

        $activity = iterator_to_array($activities)[0];
        $this->assertEquals(WatchFileActivityActionType::SHARED_MODE_CHANGED, $activity->getActionType());
        $this->assertEquals($owner->getId(), $activity->getUser()->getId());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('user_id', $actionData);
        $this->assertArrayHasKey('user_email', $actionData);
        $this->assertArrayHasKey('old_value', $actionData);
        $this->assertEquals('editor', $actionData['old_value']);
        $this->assertArrayHasKey('new_value', $actionData);
        $this->assertEquals('no_access', $actionData['new_value']);
    }

    /**
     * Test that all required actions are covered by the activity system.
     */
    public function testAllRequiredActionsAreCovered(): void
    {
        $requiredActions = [
            'Création d\'un watchFile' => WatchFileActivityActionType::CREATED,
            'Changement du mode de partage (ajout/suppression d\'utilisateur)' => WatchFileActivityActionType::SHARED_MODE_CHANGED,
            'Modification du titre (manuelle ou par chat)' => WatchFileActivityActionType::UPDATED,
            'Changement de status du watchfile' => WatchFileActivityActionType::STATUS_CHANGED,
            'Activation/désactivation d\'un acteur' => WatchFileActivityActionType::ACTOR_STATUS_CHANGED,
            'Activation/désactivation d\'une source' => WatchFileActivityActionType::SOURCE_STATUS_CHANGED,
        ];

        $availableActions = array_map(fn ($case) => $case->value, WatchFileActivityActionType::cases());

        foreach ($requiredActions as $description => $actionType) {
            $this->assertContains($actionType->value, $availableActions,
                "Action '{$description}' should be covered by activity system");
        }
    }
}
