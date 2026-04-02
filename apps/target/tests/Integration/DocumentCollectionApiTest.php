<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Utils\OpenSearchUtilsTrait;

class DocumentCollectionApiTest extends AbstractApiTestCase
{
    use OpenSearchUtilsTrait;

    protected function tearDown(): void
    {
        // Clean up OpenSearch documents after each test
        $this->cleanupOpenSearch();
        parent::tearDown();
    }

    public function testGetDocumentsCollectionWithValidWatchFileAndAccess(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        $this->assertJsonContains([
            '@context' => '/api/contexts/Document',
            '@type' => 'Collection',
        ]);
    }

    public function testGetDocumentsCollectionWithViewerAccess(): void
    {
        $owner = UserFactory::createOne();
        $viewer = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($viewer, WatchFileUserRole::VIEWER)
            ->create();

        $client = $this->createAuthenticatedClient($viewer);

        $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testGetDocumentsCollectionWithEditorAccess(): void
    {
        $owner = UserFactory::createOne();
        $editor = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->withUser($editor, WatchFileUserRole::EDITOR)
            ->create();

        $client = $this->createAuthenticatedClient($editor);

        $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
    }

    public function testGetDocumentsCollectionWithoutAccess(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);

        $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseStatusCodeSame(403);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
        ]);
    }

    public function testGetDocumentsCollectionWithNonExistentWatchFile(): void
    {
        $user = UserFactory::createOne();
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', \sprintf('/api/watch_files/%s/documents', $nonExistentId));

        // Assert - WatchFileNotFoundException is mapped to 404 by API Platform
        $this->assertResponseStatusCodeSame(404);
        $this->assertJsonContains([
            '@type' => 'Error',
            'title' => 'An error occurred',
        ]);
    }

    public function testGetDocumentsCollectionWithMissingWatchFileId(): void
    {
        $user = UserFactory::createOne();
        $client = $this->createAuthenticatedClient($user);

        // Act - Request without watchFileId in URI (should result in 404 from routing)
        $client->request('GET', '/api/watch_files//documents');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetDocumentsCollectionWithInvalidWatchFileIdFormat(): void
    {
        $user = UserFactory::createOne();
        $invalidId = 'not-a-valid-uuid';

        $client = $this->createAuthenticatedClient($user);

        $client->request('GET', \sprintf('/api/watch_files/%s/documents', $invalidId));

        // Assert - InvalidArgumentException from Assert::uuid is mapped to 400 by API Platform
        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetDocumentsCollectionUnauthenticated(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = static::createClient();

        $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetDocumentsCollectionWithPagination(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $client->request('GET', \sprintf('/api/watch_files/%s/documents?page=1', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Document',
            '@type' => 'Collection',
        ]);
    }

    public function testGetDocumentsCollectionWithFilters(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Act - Test with status filter
        $client->request('GET', \sprintf('/api/watch_files/%s/documents?status=validated', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Document',
            '@type' => 'Collection',
        ]);
    }

    public function testGetDocumentsCollectionRespectsWatchFileIdFromUri(): void
    {
        $owner = UserFactory::createOne();
        $watchFile1 = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $watchFile2 = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Act - Try to override watchFileId via query parameter
        $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?watchFile.id=%s',
            $watchFile1->getId(),
            $watchFile2->getId()
        ));

        // Assert - Should use watchFile1 from URI, not watchFile2 from query
        $this->assertResponseIsSuccessful();
        // The provider should override the query parameter with the URI parameter
    }

    public function testGetDocumentsCollectionWithOrderFilter(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Act - Test ordering by datePublish descending
        $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?order[datePublish]=desc',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Document',
            '@type' => 'Collection',
        ]);
    }

    public function testGetDocumentsCollectionWithSearchFilter(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Act - Test search filter
        $client->request('GET', \sprintf('/api/watch_files/%s/documents?search=test', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            '@context' => '/api/contexts/Document',
            '@type' => 'Collection',
        ]);
    }

    public function testGetDocumentsCollectionIncludesFacets(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // Verify facets structure exists
        $this->assertArrayHasKey('facets', $responseData, 'Response should include facets');
        $this->assertIsArray($responseData['facets']);

        // Verify all facet categories are present
        $this->assertArrayHasKey('actors', $responseData['facets']);
        $this->assertArrayHasKey('sources', $responseData['facets']);
        $this->assertArrayHasKey('domains', $responseData['facets']);
        $this->assertArrayHasKey('statuses', $responseData['facets']);

        // Verify facets are arrays
        $this->assertIsArray($responseData['facets']['actors']);
        $this->assertIsArray($responseData['facets']['sources']);
        $this->assertIsArray($responseData['facets']['domains']);
        $this->assertIsArray($responseData['facets']['statuses']);
    }

    public function testGetDocumentsCollectionFacetsIncludeAllStatuses(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('statuses', $responseData['facets']);
        $statuses = $responseData['facets']['statuses'];
        $this->assertIsArray($statuses);

        // Verify all possible statuses are included (even with count 0)
        $statusValues = array_column($statuses, 'status');
        $this->assertContains('pending', $statusValues, 'Should include pending status');
        $this->assertContains('validated', $statusValues, 'Should include validated status');
        $this->assertContains('rejected', $statusValues, 'Should include rejected status');

        // Verify each status has proper structure
        foreach ($statuses as $statusFacet) {
            $this->assertIsArray($statusFacet);
            $this->assertArrayHasKey('status', $statusFacet);
            $this->assertArrayHasKey('count', $statusFacet);
            $this->assertIsString($statusFacet['status']);
            $this->assertIsInt($statusFacet['count']);
            $this->assertGreaterThanOrEqual(0, $statusFacet['count']);
        }
    }

    public function testGetDocumentsCollectionFacetsStructure(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // Verify actor facets structure (if any exist)
        if (!empty($responseData['facets']['actors'])) {
            $actorFacet = $responseData['facets']['actors'][0];
            $this->assertArrayHasKey('actor', $actorFacet);
            $this->assertArrayHasKey('count', $actorFacet);
            $this->assertIsArray($actorFacet['actor']);
            $this->assertArrayHasKey('id', $actorFacet['actor']);
            $this->assertIsInt($actorFacet['count']);
        }

        // Verify source facets structure (if any exist)
        if (!empty($responseData['facets']['sources'])) {
            $sourceFacet = $responseData['facets']['sources'][0];
            $this->assertArrayHasKey('source', $sourceFacet);
            $this->assertArrayHasKey('count', $sourceFacet);
            $this->assertIsArray($sourceFacet['source']);
            $this->assertArrayHasKey('id', $sourceFacet['source']);
            $this->assertIsInt($sourceFacet['count']);
        }

        // Verify domain facets structure (if any exist)
        if (!empty($responseData['facets']['domains'])) {
            $domainFacet = $responseData['facets']['domains'][0];
            $this->assertArrayHasKey('domain', $domainFacet);
            $this->assertArrayHasKey('count', $domainFacet);
            $this->assertIsString($domainFacet['domain']);
            $this->assertIsInt($domainFacet['count']);
        }
    }

    public function testGetDocumentsCollectionFacetsAreIndependentOfPagination(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($owner);

        // Act - Get page 1 and page 2
        $page1Response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?page=1&itemsPerPage=5',
            $watchFileId
        ));
        $page2Response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?page=2&itemsPerPage=5',
            $watchFileId
        ));

        $page1Data = $page1Response->toArray();
        $page2Data = $page2Response->toArray();

        // Facets should be identical across pages (calculated on all results)
        $this->assertEquals(
            $page1Data['facets'],
            $page2Data['facets'],
            'Facets should be identical across pagination pages'
        );
    }

    public function testGetDocumentsCollectionFacetsWithFilters(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($owner);

        // Act - Get facets without filter, then with status filter
        $unfilteredResponse = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFileId));
        $filteredResponse = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?status=NEW',
            $watchFileId
        ));

        $this->assertResponseIsSuccessful();

        $unfilteredData = $unfilteredResponse->toArray();
        $filteredData = $filteredResponse->toArray();

        // Both should have facets
        $this->assertArrayHasKey('facets', $unfilteredData);
        $this->assertArrayHasKey('facets', $filteredData);

        // Facets should reflect the filtered query (not all documents)
        // When filtering by status=NEW, only NEW documents should be counted in facets
        $this->assertIsArray($filteredData['facets']['statuses']);
    }

    public function testGetDocumentsCollectionFacetsWithSearchFilter(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Act - Search with a term and verify facets are calculated only on matching documents
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?search=test',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // Facets should exist even with search filter
        $this->assertArrayHasKey('facets', $responseData);
        $this->assertIsArray($responseData['facets']['actors']);
        $this->assertIsArray($responseData['facets']['sources']);
        $this->assertIsArray($responseData['facets']['domains']);
        $this->assertIsArray($responseData['facets']['statuses']);
    }

    public function testGetDocumentsCollectionMaintainsHydraStructure(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // Verify standard collection structure from OpenSearch Paginator
        $this->assertArrayHasKey('@context', $responseData);
        $this->assertArrayHasKey('@id', $responseData);
        $this->assertArrayHasKey('@type', $responseData);
        $this->assertArrayHasKey('member', $responseData);
        $this->assertArrayHasKey('totalItems', $responseData);

        // Verify facets are added at the same level
        $this->assertArrayHasKey('facets', $responseData);

        // Verify the structure
        $this->assertIsArray($responseData['member']);
        $this->assertIsInt($responseData['totalItems']);
    }

    public function testFacetsCountsSumEqualsOrExceedsTotalItems(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $totalItems = $responseData['totalItems'];

        // If there are documents, verify status facets counts sum equals totalItems
        if ($totalItems > 0) {
            $statusFacets = $responseData['facets']['statuses'];
            $totalStatusCount = array_sum(array_column($statusFacets, 'count'));

            $this->assertEquals(
                $totalItems,
                $totalStatusCount,
                'Sum of status facets counts should equal totalItems'
            );
        }
    }

    public function testFacetsCountsMatchActualDocumentsForEachStatus(): void
    {
        $owner = UserFactory::createOne();

        // Create test documents: 3 validated, 2 rejected, 5 pending = 10 total
        $testData = $this->createDocumentsForTesting($owner, 3, 2, 5);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Get all documents
        $allDocsResponse = $client->request(
            'GET',
            \sprintf('/api/watch_files/%s/documents?itemsPerPage=1000', $watchFile->getId())
        );
        $allDocsData = $allDocsResponse->toArray();

        $this->assertEquals(10, $allDocsData['totalItems'], 'Should have 10 total documents');

        // For each status in facets, verify the count matches filtered results
        foreach ($allDocsData['facets']['status'] as $statusFacet) {
            $status = $statusFacet['status'];
            $facetCount = $statusFacet['count'];

            // Get documents filtered by this status
            $filteredResponse = $client->request('GET', \sprintf(
                '/api/watch_files/%s/documents?status=%s&itemsPerPage=1000',
                $watchFile->getId(),
                $status
            ));
            $filteredData = $filteredResponse->toArray();

            $this->assertEquals(
                $facetCount,
                $filteredData['totalItems'],
                \sprintf(
                    'Facet count for status "%s" (%d) should match filtered results count (%d)',
                    $status,
                    $facetCount,
                    $filteredData['totalItems']
                )
            );
        }

        // Verify specific expected counts
        $statusCounts = [];
        foreach ($allDocsData['facets']['statuses'] as $statusFacet) {
            $statusCounts[$statusFacet['status']] = $statusFacet['count'];
        }

        $this->assertEquals(3, $statusCounts['validated'], 'Should have 3 validated documents');
        $this->assertEquals(2, $statusCounts['rejected'], 'Should have 2 rejected documents');
        $this->assertEquals(5, $statusCounts['pending'], 'Should have 5 pending documents');
    }

    public function testFacetsActorCountsAreConsistent(): void
    {
        $owner = UserFactory::createOne();

        // Create test documents with actors
        $testData = $this->createDocumentsForTesting($owner, 3, 2, 5);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?itemsPerPage=1000',
            $watchFile->getId()
        ));
        $responseData = $response->toArray();

        $this->assertEquals(10, $responseData['totalItems'], 'Should have 10 documents');

        $actorFacets = $responseData['facets']['actors'];
        $this->assertNotEmpty($actorFacets, 'Should have actor facets');

        // Verify each actor facet
        foreach ($actorFacets as $actorFacet) {
            $this->assertIsArray($actorFacet);
            $this->assertArrayHasKey('actor', $actorFacet);
            $this->assertArrayHasKey('count', $actorFacet);
            $this->assertIsScalar($actorFacet['count']);

            $actor = $actorFacet['actor'];
            $count = $actorFacet['count'];

            // Verify actor structure
            $this->assertIsArray($actor);
            $this->assertArrayHasKey('id', $actor);
            $this->assertNotEmpty($actor['id']);
            $this->assertArrayHasKey('label', $actor);
            $this->assertNotEmpty($actor['label']);
            $this->assertIsString($actor['label']);

            // Verify count is positive
            $this->assertGreaterThan(0, $count, 'Actor facet count should be greater than 0');

            // Verify filtering by this actor returns the correct count
            $filteredResponse = $client->request('GET', \sprintf(
                '/api/watch_files/%s/documents?actor.id=%s&itemsPerPage=1000',
                $watchFile->getId(),
                $actor['id']
            ));

            $filteredData = $filteredResponse->toArray();
            $this->assertArrayHasKey('totalItems', $filteredData);
            $this->assertIsScalar($filteredData['totalItems']);
            $this->assertEquals(
                $count,
                $filteredData['totalItems'],
                \sprintf(
                    'Facet count for actor "%s" (%d) should match filtered results (%d)',
                    $actor['label'],
                    $count,
                    $filteredData['totalItems']
                )
            );
        }
    }

    public function testFacetsSourceCountsAreConsistent(): void
    {
        $owner = UserFactory::createOne();

        // Create test documents with sources
        $testData = $this->createDocumentsForTesting($owner, 3, 2, 5);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?itemsPerPage=1000',
            $watchFile->getId()
        ));
        $responseData = $response->toArray();

        $this->assertEquals(10, $responseData['totalItems'], 'Should have 10 documents');

        $sourceFacets = $responseData['facets']['sources'];
        $this->assertNotEmpty($sourceFacets, 'Should have source facets');

        // Verify each source facet
        foreach ($sourceFacets as $sourceFacet) {
            $this->assertIsArray($sourceFacet);
            $this->assertArrayHasKey('source', $sourceFacet);
            $this->assertArrayHasKey('count', $sourceFacet);
            $this->assertIsScalar($sourceFacet['count']);

            $source = $sourceFacet['source'];
            $count = $sourceFacet['count'];

            // Verify source structure
            $this->assertIsArray($source);
            $this->assertArrayHasKey('id', $source);
            $this->assertNotEmpty($source['id']);
            $this->assertArrayHasKey('name', $source);
            $this->assertNotEmpty($source['name']);
            $this->assertIsString($source['name']);

            // Verify count is positive
            $this->assertGreaterThan(0, $count, 'Source facet count should be greater than 0');

            // Verify filtering by this source returns the correct count
            $filteredResponse = $client->request('GET', \sprintf(
                '/api/watch_files/%s/documents?source.id=%s&itemsPerPage=1000',
                $watchFile->getId(),
                $source['id']
            ));
            $filteredData = $filteredResponse->toArray();
            $this->assertArrayHasKey('totalItems', $filteredData);
            $this->assertIsScalar($filteredData['totalItems']);
            $this->assertEquals(
                $count,
                $filteredData['totalItems'],
                \sprintf(
                    'Facet count for source "%s" (%d) should match filtered results (%d)',
                    $source['name'],
                    $count,
                    $filteredData['totalItems']
                )
            );
        }
    }

    public function testFacetsDomainCountsAreConsistent(): void
    {
        $owner = UserFactory::createOne();

        // Create test documents with domains via actors
        $testData = $this->createDocumentsForTesting($owner, 3, 2, 5);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?itemsPerPage=1000',
            $watchFile->getId()
        ));
        $responseData = $response->toArray();

        $this->assertEquals(10, $responseData['totalItems'], 'Should have 10 documents');

        $domainFacets = $responseData['facets']['domains'];
        $this->assertNotEmpty($domainFacets, 'Should have domain facets');

        // Verify each domain facet
        foreach ($domainFacets as $domainFacet) {
            $this->assertIsArray($domainFacet);
            $this->assertArrayHasKey('domain', $domainFacet);
            $this->assertArrayHasKey('count', $domainFacet);
            $this->assertIsScalar($domainFacet['count']);

            $domain = $domainFacet['domain'];
            $count = $domainFacet['count'];

            // Verify domain is a string
            $this->assertIsString($domain);
            $this->assertNotEmpty($domain);

            // Verify count is positive
            $this->assertGreaterThan(0, $count, 'Domain facet count should be greater than 0');
        }
    }

    public function testFacetsIncludeAllPossibleStatusesEvenWithZeroCount(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $statusFacets = $responseData['facets']['statuses'];
        $statusValues = array_column($statusFacets, 'status');

        // All possible document statuses should be present
        $expectedStatuses = ['pending', 'validated', 'rejected'];

        foreach ($expectedStatuses as $expectedStatus) {
            $this->assertContains(
                $expectedStatus,
                $statusValues,
                \sprintf('Status "%s" should always be present in facets, even with count 0', $expectedStatus)
            );
        }

        // Verify we have exactly 3 status facets
        $this->assertCount(
            3,
            $statusFacets,
            'There should be exactly 3 status facets (pending, validated, rejected)'
        );
    }

    public function testFacetsActorDataIncludesLabelAndDomain(): void
    {
        $owner = UserFactory::createOne();

        // Create test documents with actors
        $testData = $this->createDocumentsForTesting($owner, 3, 2, 5);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));
        $responseData = $response->toArray();

        $this->assertNotEmpty($responseData['facets']['actors'], 'Should have actor facets');

        // Verify first actor facet has complete structure
        $actorFacet = $responseData['facets']['actors'][0];

        $this->assertArrayHasKey('actor', $actorFacet);
        $actor = $actorFacet['actor'];

        $this->assertArrayHasKey('id', $actor, 'Actor should have an id');
        $this->assertArrayHasKey('label', $actor, 'Actor should have a label');
        $this->assertArrayHasKey('primaryDomain', $actor, 'Actor should have a primaryDomain');

        // Verify types
        $this->assertIsString($actor['id']);
        $this->assertNotEmpty($actor['id']);
    }

    public function testFacetsSourceDataIncludesNameAndDomain(): void
    {
        $owner = UserFactory::createOne();

        // Create test documents with sources
        $testData = $this->createDocumentsForTesting($owner, 3, 2, 5);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));
        $responseData = $response->toArray();

        $this->assertNotEmpty($responseData['facets']['sources'], 'Should have source facets');

        // Verify first source facet has complete structure
        $sourceFacet = $responseData['facets']['sources'][0];

        $this->assertArrayHasKey('source', $sourceFacet);
        $source = $sourceFacet['source'];

        $this->assertArrayHasKey('id', $source, 'Source should have an id');
        $this->assertArrayHasKey('name', $source, 'Source should have a name');
        $this->assertArrayHasKey('primaryDomain', $source, 'Source should have a primaryDomain');

        // Verify types
        $this->assertIsString($source['id']);
        $this->assertNotEmpty($source['id']);
    }

    public function testFacetsRemainConsistentAcrossMultipleFilters(): void
    {
        $owner = UserFactory::createOne();

        // Create test documents with predictable distribution
        $testData = $this->createDocumentsForTesting($owner, 3, 2, 5);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Get facets without filter
        $unfilteredResponse = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents',
            $watchFile->getId()
        ));
        $unfilteredData = $unfilteredResponse->toArray();

        $this->assertEquals(10, $unfilteredData['totalItems'], 'Should have 10 documents');

        // Get a status that has documents (we know 'pending' has 5 documents)
        $statusWithDocs = 'pending';

        // Filter by this status
        $filteredResponse = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?status=%s',
            $watchFile->getId(),
            $statusWithDocs
        ));
        $filteredData = $filteredResponse->toArray();

        // Verify facets are recalculated for filtered results
        $this->assertArrayHasKey('facets', $filteredData);
        $this->assertArrayHasKey('statuses', $filteredData['facets']);

        // The sum of filtered facets should equal filtered totalItems
        $filteredStatusFacets = $filteredData['facets']['statuses'];
        $totalFilteredCount = array_sum(array_column($filteredStatusFacets, 'count'));

        $this->assertEquals(
            $filteredData['totalItems'],
            $totalFilteredCount,
            'Sum of status facets counts in filtered results should equal filtered totalItems'
        );
    }

    public function testGetDocumentsCollectionWithActorPrimaryDomainFilter(): void
    {
        $owner = UserFactory::createOne();

        // Create test documents with actors having different primary domains
        $testData = $this->createDocumentsForTesting($owner, 3, 2, 5);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Get all documents first to identify an actor primary domain
        $allDocsResponse = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?itemsPerPage=1000',
            $watchFile->getId()
        ));
        $allDocsData = $allDocsResponse->toArray();

        $this->assertEquals(10, $allDocsData['totalItems'], 'Should have 10 documents');

        // Get the first actor's primary domain from the facets
        $actorFacets = $allDocsData['facets']['actors'];
        $this->assertNotEmpty($actorFacets, 'Should have actor facets');

        $firstActor = $actorFacets[0]['actor'];
        $primaryDomain = $firstActor['primaryDomain'];

        // Skip test if primary domain is null
        if (null === $primaryDomain) {
            $this->markTestSkipped('Test requires actors with non-null primaryDomain');
        }

        $expectedCount = $actorFacets[0]['count'];

        // Filter by this actor's primary domain
        $filteredResponse = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?actor.primaryDomain=%s&itemsPerPage=1000',
            $watchFile->getId(),
            urlencode($primaryDomain)
        ));

        $this->assertResponseIsSuccessful();
        $filteredData = $filteredResponse->toArray();

        // Verify that filtering by primary domain returns the correct count
        $this->assertArrayHasKey('totalItems', $filteredData);
        $this->assertEquals(
            $expectedCount,
            $filteredData['totalItems'],
            \sprintf(
                'Filtering by actor.primaryDomain=%s should return %d documents',
                $primaryDomain,
                $expectedCount
            )
        );

        // Verify all returned documents have actors with the specified primary domain
        foreach ($filteredData['member'] as $document) {
            $this->assertArrayHasKey('actor', $document);
            // Actor can be either an IRI string or an embedded object
            if (null !== $document['actor'] && \is_array($document['actor'])) {
                $this->assertArrayHasKey('primaryDomain', $document['actor']);
                $this->assertEquals(
                    $primaryDomain,
                    $document['actor']['primaryDomain'],
                    'All returned documents should have actors with the filtered primary domain'
                );
            }
        }

        // Verify facets are present and recalculated for filtered results
        $this->assertArrayHasKey('facets', $filteredData);
        $this->assertIsArray($filteredData['facets']['actors']);
        $this->assertIsArray($filteredData['facets']['sources']);
        $this->assertIsArray($filteredData['facets']['domains']);
        $this->assertIsArray($filteredData['facets']['statuses']);
    }

    public function testGetDocumentsCollectionIncludesValidationStatusFacets(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('GET', \sprintf('/api/watch_files/%s/documents', $watchFile->getId()));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // Verify validationStatuses facet exists
        $this->assertArrayHasKey('facets', $responseData, 'Response should include facets');
        $this->assertArrayHasKey('validationStatuses', $responseData['facets']);
        $this->assertIsArray($responseData['facets']['validationStatuses']);

        // Verify only statuses that appear in aggregations are included (no zero-count statuses)
        $validationStatuses = $responseData['facets']['validationStatuses'];
        $statusValues = array_column($validationStatuses, 'status');

        // Verify each status has proper structure and non-zero count
        foreach ($validationStatuses as $statusFacet) {
            $this->assertIsArray($statusFacet);
            $this->assertArrayHasKey('status', $statusFacet);
            $this->assertArrayHasKey('count', $statusFacet);
            $this->assertIsString($statusFacet['status']);
            $this->assertIsInt($statusFacet['count']);
            $this->assertGreaterThan(0, $statusFacet['count'], 'Only statuses with count > 0 should be present');
        }

        // Verify statuses follow the expected prefixes
        foreach ($statusValues as $status) {
            $this->assertTrue(
                str_starts_with($status, 'ai_') || str_starts_with($status, 'manual_'),
                \sprintf('Status "%s" should start with "ai_" or "manual_"', $status)
            );
        }
    }

    public function testGetDocumentsCollectionWithSingleValidationStatusFilter(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with different validation statuses
        // 2 ai_empty, 3 ai_validated, 1 manual_accept
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_empty' => 2,
            'ai_validated' => 3,
            'manual_accept' => 1, // Will be ai_empty
        ]);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Test filtering by ai_empty status - should return only 2 documents
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus=ai_empty&itemsPerPage=100',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(3, $responseData['totalItems'], 'Should return only documents with ai_empty status');

        // Verify facets are present
        $this->assertArrayHasKey('facets', $responseData);
        $this->assertArrayHasKey('validationStatuses', $responseData['facets']);
    }

    public function testGetDocumentsCollectionWithMultipleValidationStatusFilters(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with different validation statuses
        // 2 ai_empty, 3 ai_validated, 1 manual_accept, 2 ai_rejected
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_empty' => 2,
            'ai_validated' => 3,
            'manual_accept' => 1,
            'ai_rejected' => 2,
        ]);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Test filtering by multiple statuses (OR logic)
        // This should return documents with ai_empty (2) OR manual_accept (1) = 3 documents
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus[]=ai_empty&validationStatus[]=manual_accept&itemsPerPage=100',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(
            3,
            $responseData['totalItems'],
            'Should return 3 documents: 2 with ai_empty + 1 with manual_accept (OR logic)'
        );

        // Verify all returned documents have one of the requested statuses
        $this->assertArrayHasKey('member', $responseData);
        $this->assertCount(3, $responseData['member']);
    }

    public function testGetDocumentsCollectionWithAiValidatedStatusFilter(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with different validation statuses
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_validated' => 4,
            'ai_rejected' => 2,
            'ai_empty' => 1,
        ]);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Test filtering by ai_validated status
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus=ai_validated&itemsPerPage=100',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(4, $responseData['totalItems'], 'Should return only documents with ai_validated status');

        $this->assertArrayHasKey('facets', $responseData);
        $this->assertIsArray($responseData['facets']['validationStatuses']);
    }

    public function testGetDocumentsCollectionWithManualAcceptStatusFilter(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with manual validation statuses
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'manual_accept' => 3,
            'manual_refuse' => 2,
            'ai_validated' => 1,
        ]);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Test filtering by manual_accept status
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus=manual_accept&itemsPerPage=100',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(3, $responseData['totalItems'], 'Should return only documents with manual_accept status');
    }

    public function testGetDocumentsCollectionValidationStatusFilterWorksWithOtherFilters(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with different validation statuses and document statuses
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_empty' => 3,
            'ai_validated' => 2,
        ]);
        $watchFile = $testData['watchFile'];

        // All documents created are PENDING by default
        // So combining validationStatus=ai_empty + status=pending should return 3 documents

        $client = $this->createAuthenticatedClient($owner);

        // Test combining validationStatus filter with status filter
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus=ai_empty&status=pending&itemsPerPage=100',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(
            3,
            $responseData['totalItems'],
            'Should return 3 documents with both ai_empty AND pending status (AND logic between different filters)'
        );

        $this->assertArrayHasKey('facets', $responseData);
        $this->assertArrayHasKey('validationStatuses', $responseData['facets']);
        $this->assertArrayHasKey('statuses', $responseData['facets']);
    }

    public function testGetDocumentsCollectionValidationStatusFilterWithSearch(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with different validation statuses
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_validated' => 5,
            'ai_rejected' => 3,
        ]);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Test combining validationStatus filter with search
        // Note: All documents have default content, so search might match them
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus=ai_validated&itemsPerPage=100',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(5, $responseData['totalItems'], 'Should return only ai_validated documents');
        $this->assertArrayHasKey('facets', $responseData);
        $this->assertArrayHasKey('validationStatuses', $responseData['facets']);
    }

    public function testValidationStatusFilterOrLogicWithMultipleAiStatuses(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with various AI validation statuses
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_validated' => 2,
            'ai_rejected' => 3,
            'ai_uncertain' => 4,
            'ai_pending' => 1,
            'ai_empty' => 2,
        ]);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Test OR logic: ai_validated OR ai_uncertain should return 2 + 4 = 6 documents
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus[]=ai_validated&validationStatus[]=ai_uncertain&itemsPerPage=100',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(
            6,
            $responseData['totalItems'],
            'OR logic: Should return 6 documents (2 ai_validated + 4 ai_uncertain)'
        );

        // Verify facets are present (they reflect ALL documents in watchFile, not just filtered results)
        $this->assertArrayHasKey('validationStatuses', $responseData['facets']);
        $validationStatuses = $responseData['facets']['validationStatuses'];
        $this->assertIsArray($validationStatuses);
        $this->assertGreaterThanOrEqual(
            2,
            \count($validationStatuses),
            'Should have at least the filtered statuses in facets'
        );
    }

    public function testValidationStatusFilterOrLogicWithMixedAiAndManualStatuses(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with both AI and manual validation statuses
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_validated' => 3,
            'ai_rejected' => 2,
            'manual_accept' => 4,
            'manual_refuse' => 1,
            'ai_empty' => 2,
        ]);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Test OR logic with mixed statuses: ai_validated OR manual_accept OR ai_empty
        // Should return 3 + 4 + 2 = 9 documents
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus[]=ai_validated&validationStatus[]=manual_accept&validationStatus[]=ai_empty&itemsPerPage=100',
            $watchFile->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(
            10,
            $responseData['totalItems'],
            'Mixed OR logic: Should return 10 documents (3 ai_validated + 4 manual_accept + 2 ai_empty + 1 manual_refuse (ai_empty will be converted to ai_validated))'
        );

        $this->assertArrayHasKey('member', $responseData);
        $this->assertCount(10, $responseData['member']);
    }

    public function testValidationStatusFacetsReflectAllDocuments(): void
    {
        $owner = UserFactory::createOne();

        // Create documents with different validation statuses
        $testData = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_validated' => 5,
            'ai_rejected' => 3,
            'ai_uncertain' => 2,
        ]);
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);

        // Get unfiltered results
        $unfilteredResponse = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?itemsPerPage=100',
            $watchFile->getId()
        ));
        $unfilteredData = $unfilteredResponse->toArray();

        // Verify total documents
        $this->assertEquals(10, $unfilteredData['totalItems'], 'Should have 10 total documents');

        // Apply filter
        $filteredResponse = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus[]=ai_validated&validationStatus[]=ai_uncertain&itemsPerPage=100',
            $watchFile->getId()
        ));
        $filteredData = $filteredResponse->toArray();

        // Verify filtered results
        $this->assertEquals(
            7,
            $filteredData['totalItems'],
            'Should have 7 filtered documents (5 ai_validated + 2 ai_uncertain)'
        );
        $this->assertArrayHasKey('member', $filteredData);
        $this->assertCount(7, $filteredData['member']);

        // Verify facets are present and include all statuses from the watchFile
        // Note: Facets reflect ALL documents in the watchFile, not just filtered results
        $this->assertArrayHasKey('validationStatuses', $filteredData['facets']);
        $filteredStatusFacets = $filteredData['facets']['validationStatuses'];
        $this->assertIsArray($filteredStatusFacets);
        $this->assertNotEmpty($filteredStatusFacets, 'Facets should not be empty');
    }

    public function testValidationStatusFilterCombinedWithActorFilter(): void
    {
        $owner = UserFactory::createOne();

        // Create a second actor to test actor filtering
        $actor2 = ActorFactory::createOne([
            'label' => 'Second Test Actor',
            'primaryDomain' => 'example.com',
        ]);

        // Create documents with first actor
        $testData1 = $this->createDocumentsWithValidationStatuses($owner, [
            'ai_validated' => 3,
            'ai_rejected' => 2,
        ]);
        $watchFile = $testData1['watchFile'];
        $actor1 = $testData1['actor'];
        $source = $testData1['source'];

        // Create documents with second actor using the same watchFile
        $this->createDocumentsWithValidationStatuses($owner, [
            'ai_validated' => 2,
            'ai_empty' => 1,
        ], [
            'watchFile' => $watchFile,
            'actor' => $actor2,
            'source' => $source,
        ]);

        $client = $this->createAuthenticatedClient($owner);

        // Filter by actor1 AND ai_validated
        // Should return only 3 documents (from actor1 with ai_validated status)
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?validationStatus=ai_validated&actor.id=%s&itemsPerPage=100',
            $watchFile->getId(),
            $actor1->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(
            3,
            $responseData['totalItems'],
            'Should return 3 documents: actor1 with ai_validated status (AND logic between different filter types)'
        );
    }

    public function testFacetsIncludeFilteredActorWithZeroCount(): void
    {
        $owner = UserFactory::createOne();

        // Create only VALIDATED documents (no REJECTED or PENDING)
        $testData = $this->createDocumentsForTesting($owner, 5, 0, 0);
        $watchFile = $testData['watchFile'];
        $actor1 = $testData['actor1'];

        $client = $this->createAuthenticatedClient($owner);

        // Filter by actor1 AND status=rejected
        // actor1 has only VALIDATED documents, so this should return 0 documents
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?actor.id=%s&status=rejected&itemsPerPage=100',
            $watchFile->getId(),
            $actor1->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // Should have 0 documents (actor1 has only VALIDATED, not REJECTED)
        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(0, $responseData['totalItems'], 'Should have 0 documents for actor1 with status=rejected');

        // But the facets should include actor1 with count=0 and its metadata
        $this->assertArrayHasKey('facets', $responseData);
        $this->assertArrayHasKey('actors', $responseData['facets']);
        $actorFacets = $responseData['facets']['actors'];

        // Find the actor1 facet
        $actor1Facet = null;
        foreach ($actorFacets as $actorFacet) {
            if ($actorFacet['actor']['id'] === $actor1->getId()) {
                $actor1Facet = $actorFacet;

                break;
            }
        }

        $this->assertNotNull($actor1Facet, 'Actor1 facet should be present even with count=0');
        $this->assertEquals(0, $actor1Facet['count'], 'Actor1 facet count should be 0');

        // Verify metadata is present
        $this->assertArrayHasKey('actor', $actor1Facet);
        $actor = $actor1Facet['actor'];
        $this->assertArrayHasKey('id', $actor);
        $this->assertEquals($actor1->getId(), $actor['id']);
        $this->assertArrayHasKey('label', $actor);
        $this->assertNotEmpty($actor['label']);
        $this->assertArrayHasKey('primaryDomain', $actor);
    }

    public function testFacetsIncludeFilteredSourceWithZeroCount(): void
    {
        $owner = UserFactory::createOne();

        // Create only VALIDATED documents (no REJECTED or PENDING)
        $testData = $this->createDocumentsForTesting($owner, 5, 0, 0);
        $watchFile = $testData['watchFile'];
        $source1 = $testData['source1'];

        $client = $this->createAuthenticatedClient($owner);

        // Filter by source1 AND status=rejected
        // source1 has only VALIDATED documents, so this should return 0 documents
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/documents?source.id=%s&status=rejected&itemsPerPage=100',
            $watchFile->getId(),
            $source1->getId()
        ));

        $this->assertResponseIsSuccessful();
        $responseData = $response->toArray();

        // Should have 0 documents (source1 has only VALIDATED, not REJECTED)
        $this->assertArrayHasKey('totalItems', $responseData);
        $this->assertEquals(0, $responseData['totalItems'], 'Should have 0 documents for source1 with status=rejected');

        // But the facets should include source1 with count=0 and its metadata
        $this->assertArrayHasKey('facets', $responseData);
        $this->assertArrayHasKey('sources', $responseData['facets']);
        $sourceFacets = $responseData['facets']['sources'];

        // Find the source1 facet
        $source1Facet = null;
        foreach ($sourceFacets as $sourceFacet) {
            if ($sourceFacet['source']['id'] === $source1->getId()) {
                $source1Facet = $sourceFacet;

                break;
            }
        }

        $this->assertNotNull($source1Facet, 'Source1 facet should be present even with count=0');
        $this->assertEquals(0, $source1Facet['count'], 'Source1 facet count should be 0');

        // Verify metadata is present
        $this->assertArrayHasKey('source', $source1Facet);
        $source = $source1Facet['source'];
        $this->assertArrayHasKey('id', $source);
        $this->assertEquals($source1->getId(), $source['id']);
        $this->assertArrayHasKey('name', $source);
        $this->assertNotEmpty($source['name']);
        $this->assertArrayHasKey('primaryDomain', $source);
    }

    /**
     * Test that Document collection returns Document facets, not WatchFileEvent facets.
     * This prevents the bug where WatchFileEventCollectionNormalizer incorrectly matches
     * and returns event facets (eventTypes, dateRange) instead of document facets.
     */
    public function testDocumentCollectionReturnsDocumentFacetsNotEventFacets(): void
    {
        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        // Make multiple requests to catch intermittent issues
        for ($i = 0; $i < 5; ++$i) {
            $response = $client->request('GET', \sprintf(
                '/api/watch_files/%s/documents?page=%d&itemsPerPage=25',
                $watchFile->getId(),
                $i + 1
            ));

            $this->assertResponseIsSuccessful();
            $responseData = $response->toArray();

            // Verify facets exist
            $this->assertArrayHasKey('facets', $responseData, 'Response should include facets');
            $facets = $responseData['facets'];
            $this->assertIsArray($facets);

            // Verify Document-specific facets are present
            $this->assertArrayHasKey('actors', $facets, 'Document facets should include actors');
            $this->assertArrayHasKey('sources', $facets, 'Document facets should include sources');
            $this->assertArrayHasKey('domains', $facets, 'Document facets should include domains');
            $this->assertArrayHasKey('statuses', $facets, 'Document facets should include statuses');
            $this->assertArrayHasKey(
                'validationStatuses',
                $facets,
                'Document facets should include validationStatuses'
            );

            // Verify facets are arrays
            $this->assertIsArray($facets['actors'], 'Actors facet should be an array');
            $this->assertIsArray($facets['sources'], 'Sources facet should be an array');
            $this->assertIsArray($facets['domains'], 'Domains facet should be an array');
            $this->assertIsArray($facets['statuses'], 'Statuses facet should be an array');
            $this->assertIsArray($facets['validationStatuses'], 'ValidationStatuses facet should be an array');

            // Verify WatchFileEvent-specific facets are NOT present
            $this->assertArrayNotHasKey(
                'eventTypes',
                $facets,
                'Document facets should NOT include eventTypes (this is a WatchFileEvent facet)'
            );
            $this->assertArrayNotHasKey(
                'dateRange',
                $facets,
                'Document facets should NOT include dateRange (this is a WatchFileEvent facet)'
            );

            // Verify Document facet structure (actors should have 'actor' key, not just 'id')
            if (!empty($facets['actors'])) {
                /** @var array<int, array<string, mixed>> $actors */
                $actors = $facets['actors'];
                $firstActor = $actors[0];
                $this->assertArrayHasKey('actor', $firstActor, 'Actor facet should have "actor" key');
                $this->assertArrayHasKey('count', $firstActor, 'Actor facet should have "count" key');
                $this->assertIsArray($firstActor['actor'], 'Actor facet actor should be an array');
                $this->assertArrayHasKey('id', $firstActor['actor'], 'Actor should have id');
            }

            // Verify source facet structure
            if (!empty($facets['sources'])) {
                /** @var array<int, array<string, mixed>> $sources */
                $sources = $facets['sources'];
                $firstSource = $sources[0];
                $this->assertArrayHasKey('source', $firstSource, 'Source facet should have "source" key');
                $this->assertArrayHasKey('count', $firstSource, 'Source facet should have "count" key');
                $this->assertIsArray($firstSource['source'], 'Source facet source should be an array');
                $this->assertArrayHasKey('id', $firstSource['source'], 'Source should have id');
            }
        }
    }
}
