<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Domain\Document\DocumentSeenStatus;
use App\Domain\Document\DocumentSeenStatusGatewayInterface;
use App\Domain\User\User;
use App\Infrastructure\Document\DocumentSeenStatusDoctrineGateway;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(DocumentSeenStatusDoctrineGateway::class)]
class DocumentSeenStatusDoctrineGatewayTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private DocumentSeenStatusGatewayInterface $gateway;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->gateway = new DocumentSeenStatusDoctrineGateway($this->entityManager);
    }

    public function testSave(): void
    {
        $status = new DocumentSeenStatus();

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($status);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->gateway->save($status);
    }

    public function testFindByUserAndDocument(): void
    {
        $user = new User(Uuid::v4()->toString(), 'test@example.com', [], 'Tester');
        $documentId = Uuid::v4()->toString();
        $expected = new DocumentSeenStatus();

        $repository = $this->createMock(EntityRepository::class);
        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(DocumentSeenStatus::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findOneBy')
            ->with($this->callback(function (array $criteria) use ($user, $documentId) {
                return isset($criteria['user'], $criteria['documentId'])
                    && $criteria['user'] === $user
                    && $criteria['documentId'] instanceof Uuid
                    && $criteria['documentId']->toString() === $documentId;
            }))
            ->willReturn($expected);

        $result = $this->gateway->findByUserAndDocument($user, $documentId);
        $this->assertSame($expected, $result);
    }

    public function testAreDocumentsSeenSingleDocument(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'user1');
        $documentId = '550e8400-e29b-41d4-a716-446655440001';
        $documentUuid = Uuid::fromString($documentId);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('dss.documentId')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('from')
            ->with(DocumentSeenStatus::class, 'dss')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('dss.user = :user')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('dss.documentId IN (:documentIds)')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([
                [
                    'documentId' => $documentUuid,
                ],
            ]);

        $result = $this->gateway->areDocumentsSeen($user, [$documentId]);

        $this->assertEquals([
            $documentId => true,
        ], $result);
    }

    public function testAreDocumentsSeenMultipleDocuments(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'user1');
        $documentId1 = '550e8400-e29b-41d4-a716-446655440001';
        $documentId2 = '550e8400-e29b-41d4-a716-446655440002';
        $documentId3 = '550e8400-e29b-41d4-a716-446655440003';
        $documentUuid1 = Uuid::fromString($documentId1);
        $documentUuid2 = Uuid::fromString($documentId2);
        $documentUuid3 = Uuid::fromString($documentId3);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('dss.documentId')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('from')
            ->with(DocumentSeenStatus::class, 'dss')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('dss.user = :user')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('dss.documentId IN (:documentIds)')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        // Only document 1 and 3 are seen
        $query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([
                [
                    'documentId' => $documentUuid1,
                ],
                [
                    'documentId' => $documentUuid3,
                ],
            ]);

        $result = $this->gateway->areDocumentsSeen($user, [$documentId1, $documentId2, $documentId3]);

        $expected = [
            $documentId1 => true,
            $documentId2 => false,
            $documentId3 => true,
        ];
        $this->assertEquals($expected, $result);
    }

    public function testAreDocumentsSeenEmptyArray(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'user1');

        $this->entityManager
            ->expects($this->never())
            ->method('createQueryBuilder');

        $result = $this->gateway->areDocumentsSeen($user, []);

        $this->assertEquals([], $result);
    }

    public function testIsDocumentSeen(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'user1');
        $documentId = '550e8400-e29b-41d4-a716-446655440001';
        $documentUuid = Uuid::fromString($documentId);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->entityManager
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $queryBuilder
            ->expects($this->once())
            ->method('select')
            ->with('dss.documentId')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('from')
            ->with(DocumentSeenStatus::class, 'dss')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('where')
            ->with('dss.user = :user')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('andWhere')
            ->with('dss.documentId IN (:documentIds)')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([
                [
                    'documentId' => $documentUuid,
                ],
            ]);

        $result = $this->gateway->isDocumentSeen($user, $documentId);

        $this->assertTrue($result);
    }
}
