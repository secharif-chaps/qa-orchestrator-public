<?php

declare(strict_types=1);

namespace App\UserInterface\Command\Document;

use App\Application\Document\TriggerDocumentAiValidationAction;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\DocumentGatewayInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'document:retry-ai-validation',
    description: 'Retry AI validation for documents stuck in pending status',
)]
final class RetryStaleAiValidationCommand extends Command
{
    public function __construct(
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('minutes', 'm', InputOption::VALUE_REQUIRED, 'Minimum age in minutes for stale documents', '10')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of documents to process', '100')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show stale documents without processing')
            ->addOption(
                'no-retry',
                null,
                InputOption::VALUE_NONE,
                'Only mark as FAILED without re-dispatching validation (restore stable state)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $minutes = (int) $input->getOption('minutes');
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');
        $noRetry = (bool) $input->getOption('no-retry');

        $cutoff = new \DateTimeImmutable(\sprintf('-%d minutes', $minutes));

        $io->info(\sprintf(
            'Searching for documents with AI validation pending since before %s (%d min ago)',
            $cutoff->format('Y-m-d H:i:s'),
            $minutes,
        ));

        $staleDocuments = $this->documentGateway->findStaleAiValidationDocuments($cutoff, $limit);

        if (0 === \count($staleDocuments)) {
            $io->success('No stale documents found.');

            return Command::SUCCESS;
        }

        $io->info(\sprintf('Found %d stale document(s)', \count($staleDocuments)));

        if ($dryRun) {
            $rows = array_map(fn (array $doc) => [
                $doc['id'],
                mb_substr($doc['title'], 0, 50),
                $doc['processedAt'],
                $doc['watchFileId'] ?? '-',
            ], $staleDocuments);

            $io->table(['ID', 'Title', 'Pending since', 'WatchFile'], $rows);
            $io->note('Dry run — no documents were processed.');

            return Command::SUCCESS;
        }

        $processed = 0;
        $errors = 0;
        $mode = $noRetry ? 'fail-only' : 'retry';

        foreach ($staleDocuments as $doc) {
            try {
                $document = $this->documentGateway->get($doc['id']);
                $document->updateAiValidation(new AIValidation(
                    status: AiValidationStatus::FAILED,
                    confidenceScore: 0,
                    validationReason: null,
                    processedAt: new \DateTimeImmutable(),
                    referenceSubject: $document->getAiValidation()?->referenceSubject,
                ));
                $this->documentGateway->save($document);

                if (!$noRetry) {
                    $this->messageBus->dispatch(new TriggerDocumentAiValidationAction($doc['id']));
                }

                ++$processed;
                $label = $noRetry ? 'Marked FAILED' : 'Retried';
                $io->writeln(\sprintf(
                    '  <info>%s:</info> %s — %s',
                    $label,
                    $doc['id'],
                    mb_substr($doc['title'], 0, 60),
                ));
            } catch (\Throwable $e) {
                ++$errors;
                $io->writeln(\sprintf('  <error>Error:</error> %s — %s', $doc['id'], $e->getMessage()));

                $this->logger?->error('Failed to process stale AI validation for document', [
                    'document_id' => $doc['id'],
                    'mode' => $mode,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $actionLabel = $noRetry ? 'Marked as FAILED' : 'Retried';
        $io->success(\sprintf('%s %d document(s), %d error(s)', $actionLabel, $processed, $errors));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
