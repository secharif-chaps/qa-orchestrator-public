<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;

/**
 * Standard test complet pour Timeline API avec un vrai WatchFile
 * Test toutes les fonctionnalités de base sans cache pour vérifier que l'API fonctionne.
 */
class WatchFileTimelineStandardTest extends AbstractApiTestCase
{
    private WatchFileActivityGatewayInterface $activityGateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activityGateway = self::getContainer()->get(WatchFileActivityGatewayInterface::class);
    }

    public function testStandardTimelineWorkflow(): void
    {
        // 1. Créer un utilisateur et un WatchFile
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withCreatedBy($user)->withOwnedBy($user)->with([
            'status' => WatchFileStatus::DRAFT,
            'name' => 'Test Timeline Standard',
            'userObjective' => 'Tester l\'API Timeline',
        ])->create();

        // 2. Créer plusieurs activités pour avoir des données
        $activities = [
            [
                'type' => WatchFileActivityActionType::CREATED,
                'data' => [
                    'watch_file_name' => 'Test Timeline Standard',
                ],
            ],
            [
                'type' => WatchFileActivityActionType::UPDATED,
                'data' => [
                    'changes' => [
                        'name' => [
                            'old' => 'Old',
                            'new' => 'New',
                        ],
                    ],
                ],
            ],
            [
                'type' => WatchFileActivityActionType::STATUS_CHANGED,
                'data' => [
                    'old_status' => 'draft',
                    'new_status' => 'enabled',
                ],
            ],
            [
                'type' => WatchFileActivityActionType::ACTOR_STATUS_CHANGED,
                'data' => [
                    'actor_name' => 'Test Actor',
                    'status' => 'active',
                    'old_status' => 'inactive',
                ],
            ],
        ];

        foreach ($activities as $activity) {
            $this->createMockActivity($watchFile, $user, $activity['type'], $activity['data']);
        }
        // 3. Créer client authentifié
        $client = $this->createAuthenticatedClient($user);

        // 4. Test endpoint Timeline de base

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        // 5. Vérifier la structure de réponse
        $this->assertArrayHasKey('@type', $data);
        $this->assertEquals('GroupedWatchFileActivityDto', $data['@type']);
        $this->assertArrayHasKey('activitiesByDay', $data);
        $this->assertArrayHasKey('totalItems', $data);

        // 6. Vérifier les données
        $this->assertEquals(1, $data['totalItems']); // Number of days
        $this->assertEquals(4, $data['totalActivities']); // Number of activities
        $this->assertIsArray($data['activitiesByDay']);

        // 7. Vérifier la structure des activités
        $activitiesByDay = $data['activitiesByDay'];
        $firstDay = array_keys($activitiesByDay)[0];
        $activities = $activitiesByDay[$firstDay];
        $this->assertIsArray($activities);
        $firstActivity = $activities[0];
        $this->assertIsArray($firstActivity);
        $this->assertArrayHasKey('id', $firstActivity);
        $this->assertArrayHasKey('actionType', $firstActivity);
        $this->assertArrayHasKey('createdAt', $firstActivity);
        $this->assertArrayHasKey('user', $firstActivity);
        $this->assertArrayHasKey('actionData', $firstActivity);

        // 8. Vérifier l'ordre chronologique (récent en premier)
        $activityTypes = array_column($activities, 'actionType');

        // 9. Test avec pagination

        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history", [
            'query' => [
                'page' => 1,
                'itemsPerPage' => 2,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertEquals(1, $data['totalItems']); // Number of days
        $this->assertEquals(4, $data['totalActivities']); // Number of activities
        $this->assertIsArray($data['activitiesByDay']); // Activities grouped by day

        // 10. Test page 2
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/history", [
            'query' => [
                'page' => 2,
                'itemsPerPage' => 2,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertIsArray($data['activitiesByDay']); // Activities grouped by day

        // 11. Test erreurs

        // WatchFile inexistant
        $response = $client->request('GET', '/api/watch_files/00000000-0000-0000-0000-000000000000/history');
        $this->assertResponseStatusCodeSame(404);

        // Pas d'authentification
        $anonymousClient = static::createClient();
        $response = $anonymousClient->request('GET', "/api/watch_files/{$watchFile->getId()}/history");
        $this->assertResponseStatusCodeSame(401);
    }

    /**
     * @param array<string, mixed> $actionData
     */
    private function createMockActivity(
        WatchFile $watchFile,
        User $user,
        WatchFileActivityActionType $actionType,
        array $actionData,
    ): void {
        $activity = new WatchFileActivity($watchFile, $user, $actionType, $actionData, $watchFile->getOrganisation());

        $this->activityGateway->save($activity);
    }
}
