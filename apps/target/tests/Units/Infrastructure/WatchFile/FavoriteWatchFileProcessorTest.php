<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Post;
use App\Application\WatchFile\Favorite\AddFavoriteWatchFileAction;
use App\Application\WatchFile\Favorite\RemoveFavoriteWatchFileAction;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\FavoriteWatchFileProcessor;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;

class FavoriteWatchFileProcessorTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private Security $security;
    private MessageBusInterface&Stub $messageBus;
    private WatchFileGatewayInterface&Stub $watchFileGateway;
    private FavoriteWatchFileProcessor $processor;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->watchFileGateway = $this->createStub(WatchFileGatewayInterface::class);
        $this->buildProcessor();
    }

    private function buildProcessor(): void
    {
        $this->processor = new FavoriteWatchFileProcessor(
            $this->messageBus,
            $this->security,
            $this->watchFileGateway,
        );
    }

    public function testProcessAddFavoriteSuccess(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBusMock = $this->createMockWithExpectations(MessageBusInterface::class);
        $watchFileGatewayMock = $this->createMockWithExpectations(WatchFileGatewayInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBusMock;
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Post();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watchfile1')
            ->willReturn($watchFile);

        $security->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (AddFavoriteWatchFileAction $action) {
                    return 'watchfile1' === $action->watchFileId && 'user1' === $action->userId;
                })
            )
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));

        $this->processor->process($watchFile, $operation, $uriVariables, $context);
    }

    public function testProcessRemoveFavoriteSuccess(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBusMock = $this->createMockWithExpectations(MessageBusInterface::class);
        $watchFileGatewayMock = $this->createMockWithExpectations(WatchFileGatewayInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBusMock;
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Delete();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watchfile1')
            ->willReturn($watchFile);

        $security->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (RemoveFavoriteWatchFileAction $action) {
                    return 'watchfile1' === $action->watchFileId && 'user1' === $action->userId;
                })
            )
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));

        $this->processor->process($watchFile, $operation, $uriVariables, $context);
    }

    public function testProcessThrowsExceptionWhenUserNotAuthenticated(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->buildProcessor();

        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Post();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User must be authenticated to favorite a watch file.');

        $this->processor->process($watchFile, $operation, $uriVariables, $context);
    }

    public function testProcessThrowsExceptionWhenWatchFileIdMissing(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->buildProcessor();

        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Post();
        $uriVariables = [];
        $context = [];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Watch file ID is required.');

        $this->processor->process($watchFile, $operation, $uriVariables, $context);
    }

    public function testProcessThrowsExceptionWhenWatchFileIdNotString(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $this->security = $security;
        $this->buildProcessor();

        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Post();
        $uriVariables = [
            'watchFileId' => 123,
        ];
        $context = [];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Watch file ID is required.');

        $this->processor->process($watchFile, $operation, $uriVariables, $context);
    }

    public function testProcessThrowsExceptionWhenUserHasNoAccess(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $watchFileGatewayMock = $this->createMockWithExpectations(WatchFileGatewayInterface::class);
        $this->security = $security;
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Post();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watchfile1')
            ->willReturn($watchFile);

        $security->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('User must have right access to favorite a watch file.');

        $this->processor->process($watchFile, $operation, $uriVariables, $context);
    }

    public function testProcessThrowsExceptionWhenUserIdMissing(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBusStub = $this->createStub(MessageBusInterface::class);
        $watchFileGatewayMock = $this->createMockWithExpectations(WatchFileGatewayInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBusStub;
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        $user = $this->createStub(User::class);
        $user->method('getId')
            ->willReturn(null);

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $operation = new Post();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watchfile1')
            ->willReturn($watchFile);

        $security->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $messageBusStub->method('dispatch')
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('User ID is required.');

        $this->processor->process($watchFile, $operation, $uriVariables, $context);
    }

    public function testProcessWithDifferentOperationTypes(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBusMock = $this->createMockWithExpectations(MessageBusInterface::class);
        $watchFileGatewayMock = $this->createMockWithExpectations(WatchFileGatewayInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBusMock;
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        // Test with a custom operation that's not Delete (should default to Add)
        $operation = $this->createStub(\ApiPlatform\Metadata\Operation::class);
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watchfile1')
            ->willReturn($watchFile);

        $security->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (AddFavoriteWatchFileAction $action) {
                    return 'watchfile1' === $action->watchFileId && 'user1' === $action->userId;
                })
            )
            ->willReturn(new \Symfony\Component\Messenger\Envelope(new \stdClass()));

        $this->processor->process($watchFile, $operation, $uriVariables, $context);
    }
}
