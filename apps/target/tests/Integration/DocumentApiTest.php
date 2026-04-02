<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Document\DocumentSeenStatusFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\Actor;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentSeenStatusGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Utils\OpenSearchUtilsTrait;
use Doctrine\ORM\EntityManagerInterface;

class DocumentApiTest extends AbstractApiTestCase
{
    use OpenSearchUtilsTrait;

    protected function tearDown(): void
    {
        $this->cleanupOpenSearch();
        parent::tearDown();
    }

    /**
     * @return array{owner: User, watchFile: WatchFile, actor: Actor, source: Source, document: Document}
     */
    private function createTestData(): array
    {
        return $this->createSingleDocumentForTesting();
    }

    public function testGetDocumentItemAsOwner(): void
    {
        $testData = $this->createTestData();
        $owner = $testData['owner'];
        $document = $testData['document'];

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/documents/' . $document->getId());

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('excerpt', $data);
        $this->assertArrayHasKey('content', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('source', $data);
        $this->assertArrayHasKey('watchFile', $data);

        $this->assertEquals($document->getId(), $data['id']);
        $this->assertEquals($document->getTitle(), $data['title']);
        $this->assertEquals($document->getExcerpt(), $data['excerpt']);
        $this->assertEquals($document->getContent(), $data['content']);
        $this->assertEquals('validated', $data['status']);
    }

    public function testGetDocumentItemAsUnauthenticatedUser(): void
    {
        $testData = $this->createTestData();
        $document = $testData['document'];

        $client = static::createClient();
        $response = $client->request('GET', '/api/documents/' . $document->getId());

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetInvalidDocument(): void
    {
        $testData = $this->createTestData();
        $owner = $testData['owner'];

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/documents/non-existent-id');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetNonExistentDocument(): void
    {
        $testData = $this->createTestData();
        $owner = $testData['owner'];

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/documents/550e8400-e29b-41d4-a716-446655440000');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetDocumentItemWithDifferentUser(): void
    {
        $testData = $this->createTestData();
        $document = $testData['document'];

        $otherUser = UserFactory::createOne([
            'email' => 'other@example.com',
            'firstName' => 'Other',
            'lastName' => 'User',
        ]);

        $client = $this->createAuthenticatedClient($otherUser);
        $client->request('GET', '/api/documents/' . $document->getId());

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetDocumentItemIsSeenField(): void
    {
        $testData = $this->createTestData();
        $owner = $testData['owner'];
        $document = $testData['document'];
        $watchFile = $testData['watchFile'];

        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/documents/' . $document->getId());

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        // By default, isSeen should be false (no DocumentSeenStatus entry)
        $this->assertArrayHasKey('isSeen', $data);
        $this->assertFalse($data['isSeen']);

        // Create a DocumentSeenStatus entry to mark the document as seen
        DocumentSeenStatusFactory::new()
            ->withUser($owner)
            ->withDocumentId($document->getId())
            ->withWatchFile($watchFile)
            ->create();

        $response = $client->request('GET', '/api/documents/' . $document->getId());
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('isSeen', $data);

        /** @var DocumentSeenStatusGatewayInterface $gateway */
        $gateway = self::getContainer()->get(DocumentSeenStatusGatewayInterface::class);

        // Reload the user from the database to ensure it has a valid identifier
        // (lazy objects may lose their identifier after API requests clear the entity manager)
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $freshOwner = $em->find(User::class, $owner->getId());
        $this->assertNotNull($freshOwner, 'Owner should exist in database');

        $isSeenInDb = $gateway->isDocumentSeen($freshOwner, $document->getId());

        $this->assertTrue($isSeenInDb, 'DocumentSeenStatus should exist in database');
        $this->assertTrue($data['isSeen'], 'isSeen should be true in API response');
    }

    public function testSearchHighlightingInTitleAndContent(): void
    {
        /** @var DocumentGatewayInterface $documentGateway */
        $documentGateway = self::getContainer()->get(DocumentGatewayInterface::class);

        $owner = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Innovation Corp',
            'primaryDomain' => 'innovation.com',
        ]);

        $source = SourceFactory::new()
            ->create([
                'name' => 'Tech News',
                'primaryDomain' => 'technews.com',
            ]);

        // Create a document with specific searchable content
        $document = new Document(
            id: null,
            title: 'Revolutionary innovation in technology',
            excerpt: 'This article discusses groundbreaking innovations.',
            type: 'html',
            datePublish: new \DateTimeImmutable('2025-01-15'),
            dateCollect: new \DateTimeImmutable('2025-01-16'),
            content: '<p>The field of technology has seen remarkable innovation over the past decade. Companies are innovating faster than ever before.</p>',
            status: DocumentStatus::VALIDATED,
        );

        $document->setWatchFile($watchFile);
        $document->setActor($actor);
        $document->setSource($source);

        $documentGateway->save($document);
        $this->refreshOpenSearchIndex();

        // Search for "innovation" which appears in both title and content
        $client = $this->createAuthenticatedClient($owner);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/documents', [
            'query' => [
                'search' => 'innovation',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertCount(1, $data['member']);

        $resultDocument = $data['member'][0];

        // Check that highlighting boolean flags are present and true
        $this->assertArrayHasKey('titleHighlighted', $resultDocument);
        $this->assertTrue($resultDocument['titleHighlighted'], 'Title should be highlighted');

        $this->assertArrayHasKey('contentHighlighted', $resultDocument);
        $this->assertTrue($resultDocument['contentHighlighted'], 'Content should be highlighted');

        // Verify the title contains the highlight markup
        $this->assertArrayHasKey('title', $resultDocument);
        $this->assertStringContainsString(
            '<mark class="mark">',
            $resultDocument['title'],
            'Title should contain highlight markup'
        );
        $this->assertStringContainsString(
            'innovation',
            strtolower($resultDocument['title']),
            'Title should contain search term'
        );
    }
}
