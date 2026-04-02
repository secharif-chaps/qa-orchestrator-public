<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Document\DocumentSeenStatusFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\Tests\Utils\OpenSearchUtilsTrait;

class DocumentSeenStatusIntegrationTest extends AbstractApiTestCase
{
    use OpenSearchUtilsTrait;

    protected function tearDown(): void
    {
        $this->cleanupOpenSearch();
        parent::tearDown();
    }

    public function testMarkDocumentAsSeenSuccessfully(): void
    {
        $testData = $this->createSingleDocumentForTesting();
        $owner = $testData['owner'];
        $document = $testData['document'];

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('POST', '/api/documents/' . $document->getId() . '/mark-seen');

        $this->assertResponseStatusCodeSame(200);
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $responseData = $response->toArray();
        $this->assertArrayHasKey('@type', $responseData);
        $this->assertArrayHasKey('@id', $responseData);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('title', $responseData);
        $this->assertArrayHasKey('content', $responseData);
        $this->assertArrayHasKey('isSeen', $responseData);
        $this->assertTrue($responseData['isSeen']);

        $documentSeenStatus = DocumentSeenStatusFactory::repository()->findOneBy([
            'documentId' => $document->getId(),
            'user' => $owner,
        ]);

        $this->assertNotNull($documentSeenStatus);
        $this->assertSame($owner->getId(), $documentSeenStatus->getUser()?->getId());
        $this->assertSame($document->getId(), $documentSeenStatus->getDocumentId()?->toString());
        $this->assertSame($testData['watchFile']->getId(), $documentSeenStatus->getWatchFile()?->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $documentSeenStatus->getSeenAt());
    }

    public function testMarkDocumentAsSeenReturns404WhenDocumentNotFound(): void
    {
        $owner = UserFactory::new()->defaultBasilUser()->create();
        $nonExistentDocumentId = '00000000-0000-0000-0000-000000000000';

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('POST', '/api/documents/' . $nonExistentDocumentId . '/mark-seen');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testMarkDocumentAsSeenReturns401WhenNotAuthenticated(): void
    {
        $testData = $this->createSingleDocumentForTesting();
        $document = $testData['document'];

        $client = self::createClient();

        $response = $client->request('POST', '/api/documents/' . $document->getId() . '/mark-seen');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testMarkDocumentAsSeenReturns400WhenInvalidDocumentId(): void
    {
        $owner = UserFactory::new()->defaultBasilUser()->create();
        $invalidDocumentId = 'invalid-uuid';

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('POST', '/api/documents/' . $invalidDocumentId . '/mark-seen');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testMarkDocumentAsSeenUpdatesDateWhenAlreadyExists(): void
    {
        $testData = $this->createSingleDocumentForTesting();
        $owner = $testData['owner'];
        $document = $testData['document'];

        $oldSeenAt = new \DateTimeImmutable('-1 day');
        $existingStatus = DocumentSeenStatusFactory::new()
            ->withUser($owner)
            ->withDocumentId($document->getId())
            ->withWatchFile($testData['watchFile'])
            ->withSeenAt($oldSeenAt)
            ->create();

        $client = $this->createAuthenticatedClient($owner);

        $response = $client->request('POST', '/api/documents/' . $document->getId() . '/mark-seen');

        $this->assertResponseStatusCodeSame(200);

        $responseData = $response->toArray();
        $this->assertArrayHasKey('@type', $responseData);
        $this->assertArrayHasKey('@id', $responseData);
        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('title', $responseData);
        $this->assertArrayHasKey('content', $responseData);
        $this->assertArrayHasKey('isSeen', $responseData);
        $this->assertTrue($responseData['isSeen']);

        $allStatuses = DocumentSeenStatusFactory::repository()->findBy([
            'documentId' => $document->getId(),
            'user' => $owner,
        ]);

        $this->assertCount(1, $allStatuses);
        $updatedStatus = $allStatuses[0];
        $this->assertSame($existingStatus->getId()?->toString(), $updatedStatus->getId()?->toString());
        $this->assertGreaterThan($oldSeenAt, $updatedStatus->getSeenAt());
    }
}
