<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command\Collect;

use App\Application\Collect\PullStreamCollectTaskDataAction;
use App\Application\Collect\PullStreamCollectTaskDataHandler;
use App\Tests\Units\Infrastructure\Collect\Stream\NullPullStreamClient;
use App\Tests\Utils\Symfony\NullMessageBus;
use App\UserInterface\Command\Collect\CollectPullStreamCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CollectPullStreamCommand::class)]
class CollectPullStreamCommandTest extends TestCase
{
    private CollectPullStreamCommand $command;
    private NullPullStreamClient $webSocketConnection;
    private CommandTester $commandTester;
    private PullStreamCollectTaskDataHandler&Stub $pullStreamCollectTaskDataHandler;

    protected function setUp(): void
    {
        $this->webSocketConnection = new NullPullStreamClient();
        $messageBus = new NullMessageBus(fn ($message) => $message instanceof PullStreamCollectTaskDataAction);
        $this->pullStreamCollectTaskDataHandler = $this->createStub(PullStreamCollectTaskDataHandler::class);
        $this->pullStreamCollectTaskDataHandler->method('__invoke')
->willReturn(true);

        $this->command = new CollectPullStreamCommand(
            $messageBus,
            'test', // environment
            $this->webSocketConnection,
            $this->pullStreamCollectTaskDataHandler
        );

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteWithoutCollectTaskIdReturnsInvalid(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::INVALID, $exitCode);
        $this->assertStringContainsString('You must provide a collect task ID', $this->commandTester->getDisplay());
    }

    public function testExecuteWithCollectTaskIdReturnsSuccess(): void
    {
        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => 'task-123',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('task-123', $this->commandTester->getDisplay());
        $this->assertStringContainsString(
            'WebSocket connection completed successfully',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteInProductionEnvironmentShowsWarning(): void
    {
        $messageBus = new NullMessageBus(fn ($message) => $message instanceof PullStreamCollectTaskDataAction);
        $handler = $this->createStub(PullStreamCollectTaskDataHandler::class);
        $handler->method('__invoke')
->willReturn(true);

        $command = new CollectPullStreamCommand(
            $messageBus,
            'prod', // production environment
            $this->webSocketConnection,
            $handler
        );

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([
            '--collect-task-id' => 'task-123',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Running in production mode', $output);
        $this->assertStringContainsString('collect:stream:push', $output);
    }

    public function testExecuteWithFailureReturnsFailure(): void
    {
        $messageBus = new NullMessageBus(fn ($message) => false); // Simulate failure
        $handler = $this->createStub(PullStreamCollectTaskDataHandler::class);
        $handler->method('__invoke')
->willReturn(false);

        $command = new CollectPullStreamCommand($messageBus, 'test', $this->webSocketConnection, $handler);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([
            '--collect-task-id' => 'task-123',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('WebSocket connection failed', $commandTester->getDisplay());
    }

    public function testExecuteWithEmptyCollectTaskIdReturnsInvalid(): void
    {
        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => '',
        ]);

        $this->assertEquals(Command::INVALID, $exitCode);
        $this->assertStringContainsString('You must provide a collect task ID', $this->commandTester->getDisplay());
    }

    public function testExecuteDisplaysConnectingMessage(): void
    {
        $exitCode = $this->commandTester->execute([
            '--collect-task-id' => 'task-123',
        ]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Connecting to WebSocket', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Press Ctrl+C to stop gracefully', $this->commandTester->getDisplay());
    }

    public function testExecuteWithExceptionReturnsFailure(): void
    {
        $messageBus = new NullMessageBus(function ($message) {
            throw new \RuntimeException('Connection error');
        });
        $handler = $this->createStub(PullStreamCollectTaskDataHandler::class);
        $handler->method('__invoke')
->willThrowException(new \RuntimeException('Connection error'));

        $command = new CollectPullStreamCommand($messageBus, 'test', $this->webSocketConnection, $handler);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([
            '--collect-task-id' => 'task-123',
        ]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Unexpected error', $commandTester->getDisplay());
        $this->assertStringContainsString('Connection error', $commandTester->getDisplay());
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('collect:stream:pull', $this->command->getName());
        $this->assertStringContainsString('Pull streaming data from provider', $this->command->getDescription());
    }

    public function testCommandHasCollectTaskIdOption(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('collect-task-id'));

        $option = $definition->getOption('collect-task-id');
        $this->assertTrue($option->isValueRequired());
        $this->assertStringContainsString('uuid', $option->getDescription());
    }
}
