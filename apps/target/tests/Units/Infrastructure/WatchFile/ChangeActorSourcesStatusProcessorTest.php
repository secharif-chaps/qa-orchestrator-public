<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use App\Application\Actor\ChangeActorStatusAction;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\ChangeActorSourcesStatusProcessor;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Dto\Actor\ChangeActorStatusInputDto;
use App\UserInterface\Dto\Actor\ChangeActorStatusOutputDto;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ChangeActorSourcesStatusProcessorTest extends TestCase
{
    use EntityUtilsTrait;
    private ChangeActorSourcesStatusProcessor $processor;
    private MessageBusInterface&Stub $messageBus;
    private Security $security;
    private WatchFileGatewayInterface&Stub $watchFileGateway;
    private Operation&Stub $operation;

    protected function setUp(): void
    {
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->security = $this->createStub(Security::class);
        $this->watchFileGateway = $this->createStub(WatchFileGatewayInterface::class);
        $this->operation = $this->createStub(Operation::class);
        $this->buildProcessor();
    }

    private function buildProcessor(): void
    {
        $this->processor = new ChangeActorSourcesStatusProcessor(
            $this->messageBus,
            $this->security,
            $this->watchFileGateway,
        );
    }

    public function testProcessSuccessfully(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');

        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id', 'id');

        $source = new Source(
            'Test Source',
            new TranslatedText(fr: 'Description FR', en: 'Description EN'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText(fr: 'Relevance FR', en: 'Relevance EN'),
            $actor
        );
        $this->forcePropertyValue($source, 'source-id', 'id');

        $inputDto = new ChangeActorStatusInputDto(
            status: ActorStatus::ACTIVE->value,
            sourceIds: ['source-1', 'source-2'],
        );

        $applicationOutputDto = new ChangeActorStatusOutputDto($actor, [$source]);

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watch-file-id')
            ->willReturn($watchFile);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ChangeActorStatusAction $action) {
                return 'watch-file-id' === $action->watchFileId
                    && 'actor-id' === $action->actorId
                    && $action->sourceIds === ['source-1', 'source-2']
                    && ActorStatus::ACTIVE === $action->newStatus;
            }))
            ->willReturn(new Envelope($applicationOutputDto, [new HandledStamp($applicationOutputDto, 'handler')]));

        // Act
        $result = $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 'actor-id',
            ]
        );

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
        $this->assertSame($actor, $result->actor);
        $this->assertSame([$source], $result->sources);
    }

    public function testProcessThrowsExceptionWhenDataIsNull(): void
    {
        // Act & Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Request body is required');

        $this->processor->process(
            null,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 'actor-id',
            ]
        );
    }

    public function testProcessThrowsExceptionWhenWatchFileIdIsMissing(): void
    {
        // Arrange
        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::ACTIVE->value, sourceIds: ['source-1']);

        // Act & Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Watch file ID must be provided in the URL');

        $this->processor->process($inputDto, $this->operation, [
            'actorId' => 'actor-id',
        ]);
    }

    public function testProcessThrowsExceptionWhenActorIdIsMissing(): void
    {
        // Arrange
        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::ACTIVE->value, sourceIds: ['source-1']);

        // Act & Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Actor ID must be provided in the URL');

        $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => 'watch-file-id',
        ]);
    }

    public function testProcessThrowsExceptionWhenWatchFileIdIsNotString(): void
    {
        // Arrange
        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::ACTIVE->value, sourceIds: ['source-1']);

        // Act & Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Watch file ID must be a string');

        $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => 123,
            'actorId' => 'actor-id',
        ]);
    }

    public function testProcessThrowsExceptionWhenActorIdIsNotString(): void
    {
        // Arrange
        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::ACTIVE->value, sourceIds: ['source-1']);

        // Act & Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Actor ID must be a string');

        $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 456,
            ]
        );
    }

    public function testProcessThrowsExceptionWhenUserIsNotAuthenticated(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        // Arrange
        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::ACTIVE->value, sourceIds: ['source-1']);

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        // Act & Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('User must be authenticated');

        $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 'actor-id',
            ]
        );
    }

    public function testProcessThrowsExceptionWhenUserIsNotUserInstance(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        // Arrange
        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::ACTIVE->value, sourceIds: ['source-1']);

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($this->createStub(\Symfony\Component\Security\Core\User\UserInterface::class));

        // Act & Assert
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('User must be authenticated');

        $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 'actor-id',
            ]
        );
    }

    public function testProcessThrowsExceptionWhenUserDoesNotHaveEditPermission(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id', 'id');

        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::ACTIVE->value, sourceIds: ['source-1']);

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watch-file-id')
            ->willReturn($watchFile);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(false);

        // Act & Assert
        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('User must have right edit on the watchfile.');

        $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 'actor-id',
            ]
        );
    }

    public function testProcessWithInactiveStatus(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');

        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id', 'id');

        $source = new Source(
            'Test Source',
            new TranslatedText(fr: 'Description FR', en: 'Description EN'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText(fr: 'Relevance FR', en: 'Relevance EN'),
            $actor
        );
        $this->forcePropertyValue($source, 'source-id', 'id');

        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::INACTIVE->value, sourceIds: ['source-1']);

        $applicationOutputDto = new ChangeActorStatusOutputDto($actor, [$source]);

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watch-file-id')
            ->willReturn($watchFile);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ChangeActorStatusAction $action) {
                return 'watch-file-id' === $action->watchFileId
                    && 'actor-id' === $action->actorId
                    && $action->sourceIds === ['source-1']
                    && ActorStatus::INACTIVE === $action->newStatus;
            }))
            ->willReturn(new Envelope($applicationOutputDto, [new HandledStamp($applicationOutputDto, 'handler')]));

        // Act
        $result = $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 'actor-id',
            ]
        );

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
        $this->assertSame($actor, $result->actor);
        $this->assertSame([$source], $result->sources);
    }

    public function testProcessWithEmptySourceIds(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');

        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id', 'id');

        $inputDto = new ChangeActorStatusInputDto(status: ActorStatus::ACTIVE->value, sourceIds: []);

        $applicationOutputDto = new ChangeActorStatusOutputDto($actor, []);

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watch-file-id')
            ->willReturn($watchFile);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ChangeActorStatusAction $action) {
                return 'watch-file-id' === $action->watchFileId
                    && 'actor-id' === $action->actorId
                    && [] === $action->sourceIds
                    && ActorStatus::ACTIVE === $action->newStatus;
            }))
            ->willReturn(new Envelope($applicationOutputDto, [new HandledStamp($applicationOutputDto, 'handler')]));

        // Act
        $result = $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 'actor-id',
            ]
        );

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
        $this->assertSame($actor, $result->actor);
        $this->assertSame([], $result->sources);
    }

    public function testConvertToUserInterfaceDto(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor-id', 'id');
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id', 'id');
        $source = new Source(
            'Test Source',
            new TranslatedText(fr: 'Description FR', en: 'Description EN'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText(fr: 'Relevance FR', en: 'Relevance EN'),
            $actor
        );
        $this->forcePropertyValue($source, 'source-id', 'id');
        $applicationDto = new ChangeActorStatusOutputDto($actor, [$source]);
        $inputDto = new ChangeActorStatusInputDto(ActorStatus::ACTIVE->value, ['source-1']);

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watch-file-id')
            ->willReturn($watchFile);
        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(true);
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($applicationDto, [new HandledStamp($applicationDto, 'handler')]));

        // Act
        $result = $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 'watch-file-id',
                'actorId' => 'actor-id',
            ]
        );

        // Assert
        $this->assertInstanceOf(ChangeActorStatusOutputDto::class, $result);
    }
}
