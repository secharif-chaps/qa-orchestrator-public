<?php

declare(strict_types=1);

namespace App\UserInterface\Command\Collect;

use App\Application\Collect\PushStreamCollectTaskDataAction;
use App\Application\Collect\PushStreamCollectTaskDataHandler;
use App\Domain\Collect\Stream\CollectDataPushStreamInterface;
use App\UserInterface\Command\SignalHandlerCommandTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

#[AsCommand(
    name: 'collect:stream:push',
    description: 'Start WebSocket server to receive pushed streaming data from provider (server mode)'
)]
final class CollectPushStreamCommand extends Command
{
    use SignalHandlerCommandTrait;
    private bool $shouldStop = false;

    public function __construct(
        private readonly CollectDataPushStreamInterface $webSocketServer,
        private readonly PushStreamCollectTaskDataHandler $pushStreamCollectTaskDataHandler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Setup signal handlers for graceful shutdown
        $this->setupSignalHandlers($io, function () {
            $this->shouldStop = true;

            if ($this->webSocketServer->isListening()) {
                $this->webSocketServer->stop();
            }
        });

        $io->comment('Starting WebSocket server...');
        $io->comment('Waiting for provider connections...');
        $io->comment('Press Ctrl+C to stop gracefully');

        try {
            $isSuccess = ($this->pushStreamCollectTaskDataHandler)(new PushStreamCollectTaskDataAction());
            if (!$isSuccess) {
                $io->error('WebSocket server failed (see logs for details, or increase verbosity with -v)');

                return Command::FAILURE;
            }

            $io->success('WebSocket server stopped successfully');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->shouldStop) {
                $io->success('WebSocket server terminated gracefully');

                return Command::SUCCESS;
            }

            if ($e instanceof HandlerFailedException) {
                $previous = $e->getPrevious();
                $errorMessage = $previous ? $previous->getMessage() : $e->getMessage();
                $io->error('Server failed: ' . $errorMessage);
            } else {
                $io->error('Unexpected error: ' . $e->getMessage());
            }

            return Command::FAILURE;
        }
    }
}
