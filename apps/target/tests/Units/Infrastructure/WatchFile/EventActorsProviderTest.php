<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Get;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\User\User;
use App\Domain\WatchFile\EventActors;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Infrastructure\WatchFile\EventActorsProvider;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

class EventActorsProviderTest extends TestCase
{
    private EntityManagerInterface&Stub $entityManager;
    private Security&MockObject $security;
    private WatchFileGatewayInterface&Stub $watchFileGateway;
    private TranslatorInterface&Stub $translator;
    private EventActorsProvider $provider;
    private User $user;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->watchFileGateway = $this->createStub(WatchFileGatewayInterface::class);
        $this->translator = $this->createStub(TranslatorInterface::class);
        $this->user = $this->createStub(User::class);
        $this->watchFile = $this->createStub(WatchFile::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $this->provider = new EventActorsProvider(
            $this->entityManager,
            $this->security,
            $this->watchFileGateway,
            $this->translator
        );
    }

    public function testProvideSuccessWithActorAddedEvent(): void
    {
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->entityManager = $entityManagerMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $userMock = $this->createMock(User::class);
        $this->user = $userMock;
        $this->buildProvider();
        $watchFileId = 'watch-file-id';
        $eventId = 'event-id';
        $actorId = 'actor-id';

        $uriVariables = [
            'watchFileId' => $watchFileId,
            'eventId' => $eventId,
        ];

        // Mock user authentication
        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        // Mock watch file access
        $watchFileGatewayMock->expects($this->once())
            ->method('getForUser')
            ->with($watchFileId, $this->user)
            ->willReturn($this->watchFile);

        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $this->watchFile)
            ->willReturn(true);

        // Mock event repository
        $event = $this->createMock(WatchFileActivity::class);
        $event->expects($this->exactly(3))
            ->method('getActionType')
            ->willReturn(WatchFileActivityActionType::ACTOR_ADDED);
        $event->expects($this->exactly(3))
            ->method('getActionData')
            ->willReturn([
                'actor_id' => $actorId,
                'actor_name' => 'Test Actor',
                'actor_type' => 'supplier',
                'score' => 0.8,
                'primary_domain' => 'example.com',
            ]);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);
        $event->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime('2024-01-15 10:30:00'));
        $event->expects($this->once())
            ->method('getId')
            ->willReturn($eventId);

        $eventRepository = $this->createMock(EntityRepository::class);
        $eventRepository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'id' => $eventId,
                'watchFile' => $this->watchFile,
            ])
            ->willReturn($event);

        $entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->with(WatchFileActivity::class)
            ->willReturn($eventRepository);

        // Mock actor for WatchFileActor
        $actor = $this->createStub(Actor::class);
        $actor->method('getId')
            ->willReturn($actorId);
        $actor->method('getLabel')
            ->willReturn('Test Actor');

        $watchFileActor = $this->createStub(WatchFileActor::class);
        $watchFileActor->method('getId')
            ->willReturn($actorId);
        $watchFileActor->method('getActor')
            ->willReturn($actor);
        $watchFileActor->method('getStatus')
            ->willReturn(ActorStatus::ACTIVE);
        $watchFileActor->method('isActive')
            ->willReturn(true);

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$watchFileActor]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('wfa')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('from')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('join')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $entityManagerMock->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        // Mock user data
        $userMock->expects($this->once())
            ->method('getId')
            ->willReturn('user-id');
        $userMock->expects($this->once())
            ->method('getFirstName')
            ->willReturn('Test');
        $userMock->expects($this->once())
            ->method('getLastName')
            ->willReturn('User');
        $userMock->expects($this->once())
            ->method('getEmail')
            ->willReturn('test@example.com');

        $operation = new Get();

        $result = $this->provider->provide($operation, $uriVariables);

        $this->assertInstanceOf(EventActors::class, $result);
        $this->assertEquals(1, $result->count);
        $this->assertCount(1, $result->actors);

        $eventData = $result->event;
        $this->assertEquals($eventId, $eventData['id']);
        $this->assertEquals('WATCHFILE_ACTOR_ADDED', $eventData['type']);
        $this->assertIsArray($eventData['user']);
        $this->assertEquals('user-id', $eventData['user']['id']);
        $this->assertEquals('Test User', $eventData['user']['name']);

        $actorData = $result->actors[0];
        $this->assertInstanceOf(WatchFileActor::class, $actorData);
        $this->assertEquals($actorId, $actorData->getId());
        $this->assertEquals('Test Actor', $actorData->getActor()->getLabel());
        $this->assertEquals(ActorStatus::ACTIVE, $actorData->getStatus());
        $this->assertTrue($actorData->isActive());
    }

    public function testProvideThrowsExceptionWhenUserNotAuthenticated(): void
    {
        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('User must be authenticated');

        $operation = new Get();
        $this->provider->provide($operation, [
            'watchFileId' => 'id',
            'eventId' => 'id',
        ]);
    }

    public function testProvideThrowsExceptionWhenWatchFileNotFound(): void
    {
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProvider();
        $watchFileId = 'non-existent';
        $eventId = 'event-id';

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        $watchFileGatewayMock->expects($this->once())
            ->method('getForUser')
            ->with($watchFileId, $this->user)
            ->willThrowException(new WatchFileNotFoundException('WatchFile not found'));

        $this->expectException(NotFoundHttpException::class);

        $operation = new Get();
        $this->provider->provide($operation, [
            'watchFileId' => $watchFileId,
            'eventId' => $eventId,
        ]);
    }

    public function testProvideThrowsExceptionWhenUserHasNoAccess(): void
    {
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProvider();
        $watchFileId = 'watch-file-id';
        $eventId = 'event-id';

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        $watchFileGatewayMock->expects($this->once())
            ->method('getForUser')
            ->with($watchFileId, $this->user)
            ->willReturn($this->watchFile);

        $this->security->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $this->watchFile)
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to access this watch file');

        $operation = new Get();
        $this->provider->provide($operation, [
            'watchFileId' => $watchFileId,
            'eventId' => $eventId,
        ]);
    }

    public function testProvideThrowsExceptionWhenEventNotFound(): void
    {
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->entityManager = $entityManagerMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProvider();
        $watchFileId = 'watch-file-id';
        $eventId = 'non-existent-event';

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        $watchFileGatewayMock->expects($this->once())
            ->method('getForUser')
            ->willReturn($this->watchFile);

        $this->security->expects($this->once())
            ->method('isGranted')
            ->willReturn(true);

        $eventRepository = $this->createMock(EntityRepository::class);
        $eventRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->with(WatchFileActivity::class)
            ->willReturn($eventRepository);

        $this->expectException(NotFoundHttpException::class);

        $operation = new Get();
        $this->provider->provide($operation, [
            'watchFileId' => $watchFileId,
            'eventId' => $eventId,
        ]);
    }

    public function testProvideReturnsEmptyActorsForEventWithoutActors(): void
    {
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->entityManager = $entityManagerMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $userMock = $this->createMock(User::class);
        $this->user = $userMock;
        $this->buildProvider();
        $watchFileId = 'watch-file-id';
        $eventId = 'event-id';

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);

        $watchFileGatewayMock->expects($this->once())
            ->method('getForUser')
            ->willReturn($this->watchFile);

        $this->security->expects($this->once())
            ->method('isGranted')
            ->willReturn(true);

        // Mock event with no actors
        $event = $this->createMock(WatchFileActivity::class);
        $event->expects($this->exactly(3))
            ->method('getActionType')
            ->willReturn(WatchFileActivityActionType::STATUS_CHANGED);
        $event->expects($this->exactly(3))
            ->method('getActionData')
            ->willReturn([
                'old_status' => 'draft',
                'new_status' => 'active',
            ]);
        $event->expects($this->once())
            ->method('getUser')
            ->willReturn($this->user);
        $event->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime());
        $event->expects($this->once())
            ->method('getId')
            ->willReturn($eventId);

        $eventRepository = $this->createMock(EntityRepository::class);
        $eventRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($event);

        $entityManagerMock->expects($this->once())
            ->method('getRepository')
            ->willReturn($eventRepository);

        // Mock user data
        $userMock->expects($this->once())
            ->method('getId')
            ->willReturn('user-id');
        $userMock->expects($this->once())
            ->method('getFirstName')
            ->willReturn('Test');
        $userMock->expects($this->once())
            ->method('getLastName')
            ->willReturn('User');
        $userMock->expects($this->once())
            ->method('getEmail')
            ->willReturn('test@example.com');

        $operation = new Get();
        $result = $this->provider->provide($operation, [
            'watchFileId' => $watchFileId,
            'eventId' => $eventId,
        ]);

        $this->assertInstanceOf(EventActors::class, $result);
        $this->assertEquals(0, $result->count);
        $this->assertEmpty($result->actors);
    }
}
