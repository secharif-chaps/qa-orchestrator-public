<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use App\Application\WatchFile\ChangeWatchFileStatusAction;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileStatus;
use App\Infrastructure\WatchFile\ChangeWatchFileStatusProcessor;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class ChangeWatchFileStatusProcessorTest extends TestCase
{
    use EntityUtilsTrait;
    private MessageBusInterface&Stub $messageBus;
    private Security $security;
    private WatchFileGatewayInterface&Stub $watchFileGateway;
    private ChangeWatchFileStatusProcessor $processor;

    protected function setUp(): void
    {
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->security = $this->createStub(Security::class);
        $this->watchFileGateway = $this->createStub(WatchFileGatewayInterface::class);
        $this->buildProcessor();
    }

    private function buildProcessor(): void
    {
        $this->processor = new ChangeWatchFileStatusProcessor(
            $this->messageBus,
            $this->security,
            $this->watchFileGateway,
        );
    }

    public function testChangeWatchFileStatus(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watch_file_id')
            ->willReturn($watchFile);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(true);

        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ChangeWatchFileStatusAction::class))
            ->willReturn(new Envelope($watchFile, [new HandledStamp($watchFile, 'handler')]));

        $result = $this->processor->process(
            null,
            $this->createStub(Operation::class),
            [
                'id' => 'watch_file_id',
                'status' => WatchFileStatus::ENABLED->value,
            ]
        );

        $this->assertSame($watchFile, $result);
    }

    public function testMissingWatchFileIdThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be provided in the URL');

        $this->processor->process(
            null,
            $this->createStub(Operation::class),
            [
                'status' => WatchFileStatus::ENABLED->value,
            ]
        );
    }

    public function testMissingStatusThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Status must be provided in the URL');

        $this->processor->process(null, $this->createStub(Operation::class), [
            'id' => 'watch_file_id',
        ]);
    }

    public function testInvalidStatusThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid status value. Valid statuses are: draft, enabled, archived');

        $this->processor->process(
            null,
            $this->createStub(Operation::class),
            [
                'id' => 'watch_file_id',
                'status' => 'invalid',
            ]
        );
    }

    public function testNonStringWatchFileIdThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be a string');

        $this->processor->process(
            null,
            $this->createStub(Operation::class),
            [
                'id' => 123,
                'status' => WatchFileStatus::ENABLED->value,
            ]
        );
    }

    public function testNonStringStatusThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Status must be a string');

        $this->processor->process(
            null,
            $this->createStub(Operation::class),
            [
                'id' => 'watch_file_id',
                'status' => 123,
            ]
        );
    }

    public function testWatchFileNotFoundThrowsException(): void
    {
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('nonexistent_id')
            ->willThrowException(new WatchFileNotFoundException('WatchFile not found'));

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('WatchFile with ID nonexistent_id not found.');

        $this->processor->process(
            null,
            $this->createStub(Operation::class),
            [
                'id' => 'nonexistent_id',
                'status' => WatchFileStatus::ENABLED->value,
            ]
        );
    }

    public function testAccessDeniedThrowsException(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFileGatewayMock = $this->createMock(WatchFileGatewayInterface::class);
        $this->watchFileGateway = $watchFileGatewayMock;
        $this->buildProcessor();
        $watchFile = new WatchFile('Test', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');

        $watchFileGatewayMock->expects($this->once())
            ->method('get')
            ->with('watch_file_id')
            ->willReturn($watchFile);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage(
            'You do not have permission to change the status of watch file with ID watch_file_id.'
        );

        $this->processor->process(
            null,
            $this->createStub(Operation::class),
            [
                'id' => 'watch_file_id',
                'status' => WatchFileStatus::ENABLED->value,
            ]
        );
    }
}
