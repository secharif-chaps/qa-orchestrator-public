<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Application\WatchFile\RenameWatchFileAction;
use App\Application\WatchFile\RenameWatchFileHandler;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Tests\Units\Infrastructure\WatchFileActivity\NullWatchFileActivityGateway;

/**
 * Simple integration test to verify that title modifications are logged in watchFile_activity.
 */
class WatchFileTitleActivityLoggingTest extends AbstractApiTestCase
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
     * Test that MANUAL title modification via API is logged in watchFile_activity.
     */
    public function testManualTitleModificationIsLoggedInActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Original Title',
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        // Make API call to manually change the title
        $response = $this->client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'New Manual Title',
            ],
        ]);

        $this->assertResponseIsSuccessful();

        // Verify that an activity was logged in watchFile_activity
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
        $this->assertEquals('Original Title', $actionData['changes']['name']['old']);
        $this->assertEquals('New Manual Title', $actionData['changes']['name']['new']);
    }

    /**
     * Test that CHAT-BASED title modification via AI function is logged in watchFile_activity.
     */
    public function testChatBasedTitleModificationIsLoggedInActivity(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Original Title',
            ])
            ->create();

        // Simulate AI function call by directly invoking the RenameWatchFileHandler
        $renameHandler = self::getContainer()->get(RenameWatchFileHandler::class);

        $action = new RenameWatchFileAction(
            watchFileId: $watchFile->getId(),
            name: 'New AI Generated Title',
            isManualRename: false
        );

        $result = $renameHandler->__invoke($action);

        // Verify that an activity was logged in watchFile_activity
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
        $this->assertEquals('Original Title', $actionData['changes']['name']['old']);
        $this->assertEquals('New AI Generated Title', $actionData['changes']['name']['new']);

        // Verify that the watchfile was NOT marked as manually set
        $this->assertFalse($result->isTitleManuallySetByUser());
    }

    /**
     * Test that both modification types create activities with correct data.
     */
    public function testBothModificationTypesCreateActivities(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Original Title',
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        // First modification: Manual via API
        $this->client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Manual Title',
            ],
        ]);

        // Second modification: Chat-based via AI function (simulate handler call)
        $renameHandler = self::getContainer()->get(RenameWatchFileHandler::class);
        $action = new RenameWatchFileAction(
            watchFileId: $watchFile->getId(),
            name: 'AI Generated Title',
            isManualRename: false
        );
        $renameHandler->__invoke($action);

        // Verify that TWO activities were logged by checking the gateway directly
        $allActivities = $this->activityGateway->getAllSaved();
        $this->assertCount(2, $allActivities);

        // Both should be UPDATED activities
        foreach ($allActivities as $activity) {
            $this->assertEquals(WatchFileActivityActionType::UPDATED, $activity->getActionType());

            $actionData = $activity->getActionData();
            $this->assertArrayHasKey('changes', $actionData);
            $this->assertIsArray($actionData['changes']);
            $this->assertArrayHasKey('name', $actionData['changes']);
        }
    }

    /**
     * Test that the activity contains the correct information structure.
     */
    public function testActivityContainsCorrectTitleChangeInformation(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Initial Title',
            ])
            ->create();

        $this->client = $this->createAuthenticatedClient($user);

        // Change title via API
        $this->client->request('PATCH', "/api/watch_files/{$watchFile->getId()}", [
            'headers' => [
                'Content-Type' => 'application/merge-patch+json',
            ],
            'json' => [
                'name' => 'Updated Title',
            ],
        ]);

        // Verify activity details
        $activities = $this->activityGateway->getByWatchFilePaginated($watchFile);
        $this->assertGreaterThan(0, \count(iterator_to_array($activities)), 'At least one activity should be logged');
        $activity = iterator_to_array($activities)[0];

        $actionData = $activity->getActionData();

        // Verify the changes structure
        $this->assertArrayHasKey('changes', $actionData);
        $this->assertIsArray($actionData['changes']);
        $changes = $actionData['changes'];

        $this->assertArrayHasKey('name', $changes);
        $this->assertIsArray($changes['name']);
        $nameChange = $changes['name'];

        $this->assertArrayHasKey('old', $nameChange);
        $this->assertArrayHasKey('new', $nameChange);
        $this->assertEquals('Initial Title', $nameChange['old']);
        $this->assertEquals('Updated Title', $nameChange['new']);
    }
}
