<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Collect\PullStreamCollectTaskDataAction;
use App\Application\Collect\PullStreamCollectTaskDataHandler;
use App\Domain\Collect\Stream\CollectDataPullStreamInterface;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use App\UserInterface\Command\Collect\CollectPullStreamCommand;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class CollectPullStreamCommandTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;
    private Application $application;
    private CollectDataPullStreamInterface&MockObject $webSocketConnectionService;
    private MessageBusInterface&Stub $messageBus;
    private PullStreamCollectTaskDataHandler&MockObject $pullStreamCollectTaskDataHandler;
    private CollectPullStreamCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $kernel = self::bootKernel();
        $this->application = new Application($kernel);

        $this->webSocketConnectionService = $this->createMock(CollectDataPullStreamInterface::class);
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->pullStreamCollectTaskDataHandler = $this->createMock(PullStreamCollectTaskDataHandler::class);
        $this->pullStreamCollectTaskDataHandler->method('__invoke')
->willReturn(true);

        $this->command = new CollectPullStreamCommand(
            $this->messageBus,
            'test',
            $this->webSocketConnectionService,
            $this->pullStreamCollectTaskDataHandler
        );

        $this->application->addCommand($this->command);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteWithMissingCollectTaskId(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString(
            'You must provide a collect task ID with --collect-task-id option',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteWithEmptyCollectTaskId(): void
    {
        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => '',
        ]);

        $this->assertSame(Command::INVALID, $exitCode);
        $this->assertStringContainsString(
            'You must provide a collect task ID with --collect-task-id option',
            $this->commandTester->getDisplay()
        );
    }

    public function testSuccessfulWebSocketConnection(): void
    {
        $collectTaskId = 'test-task-123';

        $this->pullStreamCollectTaskDataHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function (PullStreamCollectTaskDataAction $action) use ($collectTaskId) {
                return $action->collectTaskId === $collectTaskId;
            }))
            ->willReturn(true);

        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => $collectTaskId,
        ]);

        $output = $this->commandTester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Collect task: {$collectTaskId}", $output);
        $this->assertStringContainsString('Connecting to WebSocket...', $output);
        $this->assertStringContainsString('Press Ctrl+C to stop gracefully', $output);
        $this->assertStringContainsString('WebSocket connection completed successfully', $output);
    }

    public function testWebSocketConnectionFailure(): void
    {
        $collectTaskId = 'test-task-456';

        $this->pullStreamCollectTaskDataHandler
            ->expects($this->once())
            ->method('__invoke')
            ->willThrowException(new \Exception('Connection failed'));

        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => $collectTaskId,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Unexpected error: Connection failed', $output);
    }

    public function testWebSocketHandlerFailedException(): void
    {
        $collectTaskId = 'test-task-789';

        $originalException = new CollectDataStreamException('Stream connection failed');
        $handlerException = new HandlerFailedException(
            new Envelope(new PullStreamCollectTaskDataAction($collectTaskId)),
            [$originalException]
        );

        $this->pullStreamCollectTaskDataHandler
            ->expects($this->once())
            ->method('__invoke')
            ->willThrowException($handlerException);

        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => $collectTaskId,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Connection failed: Stream connection failed', $output);
    }

    public function testWebSocketConnectionMockScenario(): void
    {
        $collectTaskId = 'test-task-mock-scenario';

        // Configure the mock to simulate a connection scenario
        $this->webSocketConnectionService
            ->expects($this->never())
            ->method('isConnected');

        $this->webSocketConnectionService
            ->expects($this->never())
            ->method('disconnect');

        $this->pullStreamCollectTaskDataHandler
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn(true);

        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => $collectTaskId,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('WebSocket connection completed successfully', $output);
    }

    public function testSignalHandlingSetup(): void
    {
        if (!\extension_loaded('pcntl')) {
            $this->markTestSkipped('PCNTL extension is not available');
        }

        $collectTaskId = 'test-task-signal';

        $this->pullStreamCollectTaskDataHandler
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn(true);

        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => $collectTaskId,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testSignalHandlingWithoutPcntl(): void
    {
        // Test that the command works even without PCNTL extension
        $collectTaskId = 'test-task-no-pcntl';

        $this->pullStreamCollectTaskDataHandler
            ->expects($this->once())
            ->method('__invoke')
            ->willReturn(true);

        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => $collectTaskId,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testGracefulShutdownOnException(): void
    {
        $collectTaskId = 'test-graceful-shutdown';

        // Test exception handling without trying to modify internal state
        $handlerException = new HandlerFailedException(
            new Envelope(new PullStreamCollectTaskDataAction($collectTaskId)),
            [new \Exception('Test exception')]
        );

        $this->pullStreamCollectTaskDataHandler
            ->expects($this->once())
            ->method('__invoke')
            ->willThrowException($handlerException);

        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => $collectTaskId,
        ]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Connection failed: Test exception', $this->commandTester->getDisplay());
    }

    public function testMessageBusHandleTraitUsage(): void
    {
        $collectTaskId = 'test-handle-trait';

        // Test that the command correctly calls the handler
        $this->pullStreamCollectTaskDataHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function (PullStreamCollectTaskDataAction $action) use ($collectTaskId) {
                return $action->collectTaskId === $collectTaskId;
            }))
            ->willReturn(true);

        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => $collectTaskId,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
