<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command\Collect;

use App\Application\Collect\PushStreamCollectTaskDataHandler;
use App\Tests\Units\Infrastructure\Collect\Stream\NullPushStreamServer;
use App\UserInterface\Command\Collect\CollectPushStreamCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CollectPushStreamCommand::class)]
class CollectPushStreamCommandTest extends TestCase
{
    private CollectPushStreamCommand $command;
    private NullPushStreamServer $webSocketServer;
    private PushStreamCollectTaskDataHandler&Stub $handler;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->webSocketServer = new NullPushStreamServer();
        $this->handler = $this->createStub(PushStreamCollectTaskDataHandler::class);

        // Default: handler returns true (success)
        $this->handler
            ->method('__invoke')
            ->willReturn(true);

        $this->command = new CollectPushStreamCommand($this->webSocketServer, $this->handler);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testExecuteReturnsSuccess(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString(
            'WebSocket server stopped successfully',
            $this->commandTester->getDisplay()
        );
    }

    public function testExecuteWithFailureReturnsFailure(): void
    {
        $handler = $this->createStub(PushStreamCollectTaskDataHandler::class);
        $handler
            ->method('__invoke')
            ->willReturn(false); // Simulate failure

        $command = new CollectPushStreamCommand($this->webSocketServer, $handler);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('WebSocket server failed', $commandTester->getDisplay());
    }

    public function testExecuteDisplaysStartingMessage(): void
    {
        $exitCode = $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Starting WebSocket server', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Waiting for provider connections', $this->commandTester->getDisplay());
        $this->assertStringContainsString('Press Ctrl+C to stop gracefully', $this->commandTester->getDisplay());
    }

    public function testExecuteWithExceptionReturnsFailure(): void
    {
        $handler = $this->createStub(PushStreamCollectTaskDataHandler::class);
        $handler
            ->method('__invoke')
            ->willThrowException(new \RuntimeException('Server error'));

        $command = new CollectPushStreamCommand($this->webSocketServer, $handler);

        $commandTester = new CommandTester($command);
        $exitCode = $commandTester->execute([]);

        $this->assertEquals(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Unexpected error', $commandTester->getDisplay());
        $this->assertStringContainsString('Server error', $commandTester->getDisplay());
    }

    public function testCommandHasCorrectNameAndDescription(): void
    {
        $this->assertEquals('collect:stream:push', $this->command->getName());
        $this->assertStringContainsString('Start WebSocket server', $this->command->getDescription());
        $this->assertStringContainsString('server mode', $this->command->getDescription());
    }

    public function testCommandHasNoRequiredOptions(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertCount(0, $definition->getOptions());
    }
}
