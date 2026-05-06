<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\Source\SourceStatus;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUserRole;

class BatchChangeSourceStatusApiTest extends AbstractApiTestCase
{
    public function testBatchChangeSourceStatusActivatesMultipleSources(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source1 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::INACTIVE,
        ]);
        $source2 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::INACTIVE,
        ]);
        $source3 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [
                    [
                        'id' => $source1->getId(),
                    ],
                    [
                        'id' => $source2->getId(),
                    ],
                    [
                        'id' => $source3->getId(),
                    ],
                ],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('processed', $data);
        $this->assertArrayHasKey('failed', $data);

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('completed successfully', $data['message']);
        $this->assertEquals(3, $data['total']);
        $this->assertEquals(3, $data['processed']);
        $this->assertEquals(0, $data['failed']);
        $this->assertCount(3, $data['results']);
        $this->assertCount(0, $data['errors']);

        foreach ($data['results'] as $result) {
            $this->assertTrue($result['success']);
            $this->assertEquals('active', $result['status']);
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('watchFileId', $result);
        }

        $this->assertSourceStatus($source1->getId(), SourceStatus::ACTIVE);
        $this->assertSourceStatus($source2->getId(), SourceStatus::ACTIVE);
        $this->assertSourceStatus($source3->getId(), SourceStatus::ACTIVE);
    }

    public function testBatchChangeSourceStatusDeactivatesMultipleSources(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source1 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::ACTIVE,
        ]);
        $source2 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::ACTIVE,
        ]);
        $source3 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [
                    [
                        'id' => $source1->getId(),
                    ],
                    [
                        'id' => $source2->getId(),
                    ],
                    [
                        'id' => $source3->getId(),
                    ],
                ],
                'status' => SourceStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertEquals(3, $data['total']);
        $this->assertEquals(3, $data['processed']);
        $this->assertEquals(0, $data['failed']);

        $this->assertSourceStatus($source1->getId(), SourceStatus::INACTIVE);
        $this->assertSourceStatus($source2->getId(), SourceStatus::INACTIVE);
        $this->assertSourceStatus($source3->getId(), SourceStatus::INACTIVE);
    }

    public function testBatchChangeSourceStatusRequiresAuthentication(): void
    {
        $watchFile = WatchFileFactory::createOne();

        $client = self::createClient();
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [[
                    'id' => 'some-source-id',
                ]],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testBatchChangeSourceStatusRequiresWatchFileAccess(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
        ]);

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [[
                    'id' => $source->getId(),
                ]],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testBatchChangeSourceStatusWithEmptySources(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('No sources to process', $data['message']);
        $this->assertEquals(0, $data['total']);
        $this->assertEquals(0, $data['processed']);
        $this->assertEquals(0, $data['failed']);
    }

    public function testBatchChangeSourceStatusWithInvalidStatus(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [[
                    'id' => $source->getId(),
                ]],
                'status' => 'invalid_status',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testBatchChangeSourceStatusWithMissingStatus(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [[
                    'id' => $source->getId(),
                ]],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testBatchChangeSourceStatusWithMissingSources(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testBatchChangeSourceStatusWithNonExistentSource(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $nonExistentSourceId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [[
                    'id' => $nonExistentSourceId,
                ]],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertFalse($data['success']);
        $this->assertEquals(1, $data['total']);
        $this->assertEquals(0, $data['processed']);
        $this->assertEquals(1, $data['failed']);
        $this->assertCount(1, $data['errors']);
    }

    public function testBatchChangeSourceStatusWithWatchFileActive(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [[
                    'id' => $source->getId(),
                ]],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertFalse($data['success']);
        $this->assertEquals(1, $data['total']);
        $this->assertEquals(0, $data['processed']);
        $this->assertEquals(1, $data['failed']);
        $this->assertCount(1, $data['errors']);
        $this->assertStringContainsString('active status', $data['errors'][0]['error']);
    }

    public function testBatchChangeSourceStatusWithPartialFailures(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source1 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::INACTIVE,
        ]);
        $nonExistentSourceId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [
                    [
                        'id' => $source1->getId(),
                    ],
                    [
                        'id' => $nonExistentSourceId,
                    ],
                ],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertFalse($data['success']);
        $this->assertStringContainsString('partial success', $data['message']);
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(1, $data['processed']);
        $this->assertEquals(1, $data['failed']);
        $this->assertCount(1, $data['results']);
        $this->assertCount(1, $data['errors']);

        // Verify that source1 was successfully updated
        $this->assertSourceStatus($source1->getId(), SourceStatus::ACTIVE);
    }

    public function testBatchChangeSourceStatusWithSourcesAlreadyAtTargetStatus(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source1 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::ACTIVE,
        ]);
        $source2 = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [
                    [
                        'id' => $source1->getId(),
                    ],
                    [
                        'id' => $source2->getId(),
                    ],
                ],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(2, $data['processed']);
        $this->assertEquals(0, $data['failed']);

        // Both sources should be in results, even if source1 was already ACTIVE
        $this->assertCount(2, $data['results']);
    }

    public function testBatchChangeSourceStatusWithDifferentWatchFileIds(): void
    {
        $user = UserFactory::createOne();
        $watchFile1 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();
        $watchFile2 = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $source1 = SourceFactory::createOne([
            'watchFile' => $watchFile1,
        ]);
        $source2 = SourceFactory::createOne([
            'watchFile' => $watchFile2,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile1->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [
                    [
                        'id' => $source1->getId(),
                    ],
                    [
                        'id' => $source2->getId(),
                    ],
                ],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertFalse($data['success']);
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(0, $data['processed']);
        $this->assertEquals(2, $data['failed']);
        $this->assertCount(2, $data['errors']);

        foreach ($data['errors'] as $error) {
            $this->assertStringContainsString('All sources must belong to the same watchfile', $error['error']);
        }
    }

    public function testBatchChangeSourceStatusWithWatchFileNotFound(): void
    {
        $user = UserFactory::createOne();
        $nonExistentWatchFileId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$nonExistentWatchFileId}/sources/batch-change-status", [
            'json' => [
                'sources' => [[
                    'id' => 'some-source-id',
                ]],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testBatchChangeSourceStatusWithEmptyRequestBody(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => null,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testBatchChangeSourceStatusWithMissingRequestBody(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status");

        $this->assertResponseStatusCodeSame(422);
    }

    public function testBatchChangeSourceStatusWithSharedWatchFile(): void
    {
        $owner = UserFactory::createOne();
        $sharedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileUserFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $sharedUser,
            'role' => WatchFileUserRole::EDITOR,
        ]);

        $source = SourceFactory::createOne([
            'watchFile' => $watchFile,
            'status' => SourceStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($sharedUser);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [[
                    'id' => $source->getId(),
                ]],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['processed']);
    }

    public function testBatchChangeSourceStatusWithInvalidWatchFileId(): void
    {
        $user = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', '/api/watch_files/invalid-id/sources/batch-change-status', [
            'json' => [
                'sources' => [[
                    'id' => 'some-source-id',
                ]],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testBatchChangeSourceStatusWithInvalidSourceIdType(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => [
                    [
                        'id' => 123,
                    ], // Invalid: should be string
                    [
                        'id' => 'valid-source-id',
                    ],
                ],
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $response->toArray(false);

        $this->assertArrayHasKey('violations', $data);
        $violations = $data['violations'];

        $sourceIdViolations = array_filter($violations, function ($violation) {
            return str_contains($violation['propertyPath'] ?? '', 'sources');
        });

        $this->assertNotEmpty($sourceIdViolations);
    }

    public function testBatchChangeSourceStatusWithNonArraySources(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/sources/batch-change-status", [
            'json' => [
                'sources' => 'not-an-array',
                'status' => SourceStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * Helper method to assert the status of a source in the database.
     */
    private function assertSourceStatus(string $sourceId, SourceStatus $expectedStatus): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $source = $entityManager->getRepository(\App\Domain\Source\Source::class)->find($sourceId);

        $this->assertNotNull($source, 'Source should exist in database');
        $this->assertEquals($expectedStatus, $source->getStatus(), 'Source status should match expected value');
    }
}
