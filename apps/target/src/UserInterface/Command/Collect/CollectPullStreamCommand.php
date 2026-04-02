<?php

declare(strict_types=1);

namespace App\UserInterface\Command\Collect;

use App\Application\Collect\PullStreamCollectTaskDataAction;
use App\Application\Collect\PullStreamCollectTaskDataHandler;
use App\Domain\Collect\Stream\CollectDataPullStreamInterface;
use App\UserInterface\Command\SignalHandlerCommandTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'collect:stream:pull',
    description: 'Pull streaming data from provider via outbound WebSocket connection (client mode)'
)]
final class CollectPullStreamCommand extends Command
{
    use HandleTrait;
    use SignalHandlerCommandTrait;
    private bool $shouldStop = false;

    public function __construct(
        private MessageBusInterface $messageBus,
        private string $environment,
        private readonly CollectDataPullStreamInterface $webSocketConnection,
        private readonly PullStreamCollectTaskDataHandler $pullStreamCollectTaskDataHandler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'collect-task-id',
            'c',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Collect Task ID to monitor (required, required format is uuid)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('prod' === $this->environment) {
            $io->warning(
                'Running in production mode. Usually, this command is used in development or testing environments. ' .
                'You should prefer using the push stream command (collect:stream:push) instead.'
            );
        }

        $collectTaskId = (string) $input->getOption('collect-task-id');

        if (!$collectTaskId) {
            $io->error('You must provide a collect task ID with --collect-task-id option');

            return Command::INVALID;
        }

        // Setup signal handlers for graceful shutdown
        $this->setupSignalHandlers($io, function () {
            $this->shouldStop = true;

            if ($this->webSocketConnection->isConnected()) {
                $this->webSocketConnection->disconnect();
            }
        });

        $io->writeln(\sprintf('Collect task: %s', $collectTaskId));
        $io->comment('Connecting to WebSocket...');
        $io->comment('Press Ctrl+C to stop gracefully');

        try {
            // This is a workaround we must find deeper solution for this
            $isSuccess = $this->pullStreamCollectTaskDataHandler->__invoke(new PullStreamCollectTaskDataAction(
                $collectTaskId
            ));
            if (!$isSuccess) {
                $io->error('WebSocket connection failed (see logs for details, or increase verbosity with -v)');

                return Command::FAILURE;
            }

            $io->success('WebSocket connection completed successfully');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($this->shouldStop) {
                $io->success('WebSocket connection terminated gracefully');

                return Command::SUCCESS;
            }

            if ($e instanceof HandlerFailedException) {
                $previous = $e->getPrevious();
                $errorMessage = $previous ? $previous->getMessage() : $e->getMessage();
                $io->error('Connection failed: ' . $errorMessage);
            } else {
                $io->error('Unexpected error: ' . $e->getMessage());
            }

            return Command::FAILURE;
        }
    }
}
