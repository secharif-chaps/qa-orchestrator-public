<?php

declare(strict_types=1);

namespace App\UserInterface\Command;

use App\Application\WatchFile\Quota\CheckDocumentQuotaAction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:usage-limit:document:check',
    description: 'Checks WatchFiles for document quota violations and moves exceeding ones to DRAFT status'
)]
class CheckDocumentQuotaCommand extends Command
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'quota',
                null,
                InputOption::VALUE_OPTIONAL,
                'Override the default document quota limit (for testing purposes)',
                null
            )
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command checks all WatchFiles to identify those that exceed
                    the configured document quota limit. WatchFiles exceeding the limit are automatically
                    moved to DRAFT status and an activity log entry is created for traceability.

                    This command is designed to be run periodically via cron (e.g., every hour).

                    <info>php %command.full_name%</info>

                    To override the quota for testing purposes:

                    <info>php %command.full_name% --quota=100</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $quota = $input->getOption('quota');
        $quotaValue = null !== $quota ? (int) $quota : null;

        if (null !== $quotaValue && $quotaValue < 0) {
            $io->error('Quota must be a positive integer or zero.');

            return Command::FAILURE;
        }

        $io->title('Document Quota Check');

        if (null !== $quotaValue) {
            $io->note(\sprintf('Using custom quota: %d documents', $quotaValue));
        } else {
            $io->note('Using quota from configuration');
        }

        $io->info('Checking WatchFiles for document quota violations...');

        try {
            $action = new CheckDocumentQuotaAction($quotaValue);
            /** @var int $processedCount */
            $processedCount = $this->handle($action);

            if (0 === $processedCount) {
                $io->success('No WatchFiles exceed the document quota limit.');
            } else {
                $io->success(\sprintf(
                    'Processed %d WatchFile(s) that exceeded the document quota limit.',
                    (int) $processedCount
                ));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(\sprintf('An error occurred while checking document quota: %s', (string) $e->getMessage()));
            $io->writeln($e->getTraceAsString(), OutputInterface::VERBOSITY_VERBOSE);

            return Command::FAILURE;
        }
    }
}
