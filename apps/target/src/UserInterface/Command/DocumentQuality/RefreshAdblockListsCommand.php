<?php

declare(strict_types=1);

namespace App\UserInterface\Command\DocumentQuality;

use App\Domain\DocumentQuality\AdblockDomainListProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Manual trigger for the adblock list refresh — the same work is performed
 * automatically every 24h by `DefaultScheduleProvider` via Symfony Scheduler.
 * Useful for ops verification and for re-warming the cache after a Valkey wipe.
 */
#[AsCommand(
    name: 'adblock:refresh',
    description: 'Refresh the consolidated adblock domain list from all configured sources',
)]
class RefreshAdblockListsCommand extends Command
{
    public function __construct(
        private readonly AdblockDomainListProviderInterface $provider,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Adblock domain list refresh');

        $startedAt = microtime(true);
        $count = $this->provider->refresh();
        $elapsedMs = (int) ((microtime(true) - $startedAt) * 1000);

        $io->success(\sprintf(
            '%s unique domain(s) loaded in %s ms',
            number_format($count, thousands_separator: ' '),
            number_format($elapsedMs, thousands_separator: ' '),
        ));

        return Command::SUCCESS;
    }
}
