<?php

declare(strict_types=1);

namespace App\UserInterface\Command\Document;

use App\Application\Collect\Task\CreateCollectTaskAction;
use App\Application\Collect\Web\WebCollectConfig;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Url\UrlSourceTypeClassifierInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\DocumentQuality\Exception\QualityReportNotFoundException;
use App\Domain\DocumentQuality\QualityReportGatewayInterface;
use App\Domain\Source\ManualSourceFactory;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Webmozart\Assert\Assert;

/**
 * Manual document creation from the CLI — provider-agnostic.
 *
 * Builds a {@see CreateCollectTaskAction} carrying URL/HTML overrides in
 * its `configuration` payload and dispatches it through the standard
 * collect orchestrator. The actual fetch/extract/build work happens
 * downstream in the resolved provider's handler — `web` for MANUAL
 * sources, `apify`/`bakus` for everything else.
 *
 * Operational uses:
 * - On-call ingestion of a single URL when a provider misses it.
 * - Reproducing a flaky pipeline run from a saved HTML file.
 * - Smoke-testing a watch_file's pre/post-save processors end-to-end.
 *
 * `--sync` forces the entire chain (CreateCollectTask + provider fetch +
 * IngestDocument + post-save pipeline) onto the in-memory `sync` transport
 * so the caller can inspect the resulting document immediately. Async API
 * paths (no flag) return as soon as the bus accepts the message.
 *
 * URL classification: when `--url` is supplied without an explicit
 * `--source-id`, the URL is classified by host. Only `MANUAL` (generic
 * web) is accepted in one-shot mode for now — recognised social/video
 * platforms (Twitter, LinkedIn, YouTube, TikTok, …) are rejected with a
 * clear error message until dedicated one-shot Apify/Bakus actors land.
 */
#[AsCommand(name: 'document:create', description: 'Create a document manually from a URL or HTML file')]
class CreateDocumentCommand extends Command
{
    /**
     * Built in {@see initialize()} from the active output verbosity so
     * informational tracing (URL fetched, source resolved, etc.) routes
     * through PSR-3 instead of {@see SymfonyStyle::info()} blocks. The
     * command keeps {@see SymfonyStyle} for *user-facing* presentation
     * (success/error frames, tables, sections) — we just stop using it
     * for log-style chatter.
     */
    private LoggerInterface $logger;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly SourceGatewayInterface $sourceGateway,
        private readonly CollectTaskGatewayInterface $collectTaskGateway,
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly QualityReportGatewayInterface $qualityReportGateway,
        private readonly ManualSourceFactory $manualSourceFactory,
        private readonly UrlSourceTypeClassifierInterface $urlClassifier,
    ) {
        parent::__construct();
        $this->logger = new NullLogger();
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // ConsoleLogger pipes through the active OutputInterface. The
        // default verbosity map hides INFO/NOTICE behind `-vv`/`-v`,
        // which would silence the operational tracing we expect to see
        // by default — override the map so INFO and NOTICE are emitted
        // at VERBOSITY_NORMAL while DEBUG still requires `-vv`.
        $verbosityLevelMap = [
            LogLevel::NOTICE => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::DEBUG => OutputInterface::VERBOSITY_VERY_VERBOSE,
        ];
        $this->logger = new ConsoleLogger($output, $verbosityLevelMap);
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Build a Document from a URL or local HTML file and dispatch
                it through the standard collect orchestrator.

                <info>Async (default)</info>: dispatches CreateCollectTaskAction
                to the bus and returns immediately. Provider fetch +
                ingestion + post-save pipeline run on workers.

                <info>--sync</info>: forces the entire chain onto the in-memory
                <comment>sync</comment> transport. The command waits for everything
                to complete and prints a recap (resulting Documents, quality
                report) before exiting. Useful for one-off ingests, debug,
                and integration tests.

                Examples:
                  <info>document:create</info> WATCH_FILE_UUID --url=https://example.com/article
                  <info>document:create</info> WATCH_FILE_UUID --html-file=/tmp/page.html --sync
                  <info>document:create</info> WATCH_FILE_UUID --url=… --title="Override" --excerpt="Custom"
                HELP)
            ->addArgument('watchFileId', InputArgument::REQUIRED, 'The WatchFile UUID')
            ->addOption('url', null, InputOption::VALUE_REQUIRED, 'URL to fetch content from')
            ->addOption('html-file', null, InputOption::VALUE_REQUIRED, 'Path to a local HTML file')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'Override document title')
            ->addOption('excerpt', null, InputOption::VALUE_REQUIRED, 'Override document excerpt')
            ->addOption(
                'source-id',
                null,
                InputOption::VALUE_REQUIRED,
                'Use a specific Source UUID instead of auto-creating'
            )
            ->addOption(
                'sync',
                null,
                InputOption::VALUE_NONE,
                'Run the full chain (CreateCollectTask + provider + ingestion + post-save) on the sync transport and print a recap'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $watchFileId */
        $watchFileId = $input->getArgument('watchFileId');

        $url = $input->getOption('url');
        $htmlFile = $input->getOption('html-file');
        $titleOverride = $input->getOption('title');
        $excerptOverride = $input->getOption('excerpt');
        $sourceIdOption = $input->getOption('source-id');

        if (null === $url && null === $htmlFile) {
            $io->error('You must provide either --url or --html-file.');

            return Command::FAILURE;
        }

        try {
            $watchFile = $this->watchFileGateway->get($watchFileId);
        } catch (\Throwable $e) {
            $io->error(\sprintf('WatchFile "%s" not found: %s', $watchFileId, $e->getMessage()));

            return Command::FAILURE;
        }

        $this->logger->info('WatchFile resolved: {name} ({id})', [
            'name' => $watchFile->getName(),
            'id' => $watchFileId,
        ]);

        $sourceIdValue = \is_string($sourceIdOption) ? $sourceIdOption : null;
        $source = $this->resolveSource($sourceIdValue, $watchFile, $io);

        // URL classification: only enforced when the user did NOT pass an
        // explicit --source-id. With an explicit source, the operator
        // already knows what they're doing and bypasses the classifier
        // (e.g. a Twitter Source with its own provider routing).
        if (null === $sourceIdValue && \is_string($url) && SourceType::MANUAL === $source->getType()) {
            $detected = $this->urlClassifier->classify($url);
            if (null !== $detected && SourceType::MANUAL !== $detected) {
                $io->error(\sprintf(
                    'URL detected as %s. One-shot ingestion is not yet supported for this type — create a dedicated Source on the WatchFile and re-run with --source-id.',
                    $detected->value,
                ));

                return Command::FAILURE;
            }
        }

        // Read raw HTML once on the CLI side so the file IO error stays a
        // CLI concern (clear local error message) rather than bubbling
        // through the FetchWebUrlHandler. URL fetching is provider-side.
        $rawHtml = null;
        if (\is_string($htmlFile)) {
            if (!file_exists($htmlFile)) {
                $io->error(\sprintf('File not found: %s', $htmlFile));

                return Command::FAILURE;
            }
            $rawHtml = file_get_contents($htmlFile);
            if (false === $rawHtml || '' === $rawHtml) {
                $io->error(\sprintf('Failed to read or empty file: %s', $htmlFile));

                return Command::FAILURE;
            }
            $this->logger->info('Read HTML file: {path} ({bytes} bytes)', [
                'path' => $htmlFile,
                'bytes' => number_format(\strlen($rawHtml)),
            ]);
        }

        $sync = true === $input->getOption('sync');

        $configuration = new WebCollectConfig(
            url: \is_string($url) ? $url : null,
            rawHtml: $rawHtml,
            titleOverride: \is_string($titleOverride) ? $titleOverride : null,
            excerptOverride: \is_string($excerptOverride) ? $excerptOverride : null,
            syncChain: $sync,
        );

        $sourceId = $source->getId();

        $stamps = $sync ? [new TransportNamesStamp(['sync'])] : [];
        $envelope = $this->messageBus->dispatch(
            new CreateCollectTaskAction(
                sourceId: $sourceId,
                watchFileId: $watchFileId,
                start: true,
                configuration: $configuration->toCollectTaskConfiguration(),
            ),
            $stamps,
        );

        if (!$sync) {
            $io->success('Collect task dispatched for async ingestion.');

            return Command::SUCCESS;
        }

        $collectTask = $this->extractCollectTask($envelope);
        // The CollectTask returned by CreateCollectTaskHandler reflects its
        // own state machine post-start (QUEUED). Reload through the gateway
        // to observe transitions made by downstream sync handlers
        // (FetchWebUrlHandler bumps to RUNNING then COMPLETED inline).
        $collectTaskId = $collectTask->getId();
        Assert::stringNotEmpty($collectTaskId);
        try {
            $collectTask = $this->collectTaskGateway->get($collectTaskId);
        } catch (\Throwable) {
            // Stay with the in-memory snapshot if the reload fails (test
            // doubles, transient gateway issue) — the recap below still
            // prints something meaningful.
        }

        return $this->renderSyncRecap($io, $collectTask);
    }

    private function extractCollectTask(Envelope $envelope): CollectTask
    {
        $stamp = $envelope->last(HandledStamp::class);
        if (!$stamp instanceof HandledStamp) {
            throw new \LogicException(
                'CreateCollectTaskAction was dispatched on the sync transport but no HandledStamp came back — check that CreateCollectTaskHandler is registered.',
            );
        }

        $result = $stamp->getResult();
        if (!$result instanceof CollectTask) {
            throw new \LogicException(\sprintf(
                'CreateCollectTaskHandler must return a CollectTask; got "%s".',
                get_debug_type($result),
            ));
        }

        return $result;
    }

    private function renderSyncRecap(SymfonyStyle $io, CollectTask $collectTask): int
    {
        $collectTaskId = $collectTask->getId();
        Assert::stringNotEmpty($collectTaskId);

        $status = $collectTask->getStatus();
        $providerName = $collectTask->getProviderName();
        $providerTaskId = $collectTask->getProviderTaskId();

        if (CollectTaskStatus::FAILED === $status) {
            $io->error(\sprintf(
                'Collect task FAILED (provider=%s, providerTaskId=%s).',
                $providerName,
                $providerTaskId ?? '-',
            ));

            return Command::FAILURE;
        }

        if (CollectTaskStatus::COMPLETED !== $status) {
            $io->note(\sprintf(
                'Collect task scheduled async (provider=%s, providerTaskId=%s, status=%s). Documents will arrive when the provider completes — re-run document:read on this watch file later, or check the worker logs.',
                $providerName,
                $providerTaskId ?? '-',
                $status->value,
            ));

            return Command::SUCCESS;
        }

        $documents = $this->documentGateway->findByCollectTaskId($collectTaskId);
        if ([] === $documents) {
            $io->warning(
                'Collect task COMPLETED with no persisted Document — pipeline likely halted (duplicate detection, content quality gate). See the inline log lines above for the halt reason.'
            );

            return Command::SUCCESS;
        }

        foreach ($documents as $document) {
            if ($this->isReingestionMerge($document, $collectTaskId)) {
                // The persisted entity pre-existed and was re-touched by this
                // sync chain (providerId match → IngestDocumentHandler merge).
                // Surface it explicitly so the operator does not mistake the
                // outcome for a fresh insert.
                $io->note(\sprintf(
                    'Document MERGED into existing entry: "%s" (ID: %s). See `duplicates[]` for the audit trail.',
                    $document->getTitle(),
                    $document->getId(),
                ));
            } else {
                $io->success(\sprintf(
                    'Document persisted: "%s" (ID: %s).',
                    $document->getTitle(),
                    $document->getId(),
                ));
            }
            $this->renderQualityReport($io, $document);
        }

        return Command::SUCCESS;
    }

    /**
     * A merge re-ingestion is detected when the persisted document carries a
     * `DuplicateAttempt` entry whose `collectTaskId` matches the task we just
     * dispatched — that entry was appended by IngestDocumentHandler when it
     * found an existing document by providerId.
     */
    private function isReingestionMerge(Document $document, string $collectTaskId): bool
    {
        foreach ($document->getDuplicates() as $duplicate) {
            if ($duplicate->collectTaskId === $collectTaskId) {
                return true;
            }
        }

        return false;
    }

    private function renderQualityReport(SymfonyStyle $io, Document $document): void
    {
        try {
            $report = $this->qualityReportGateway->findByDocumentId($document->getId());
        } catch (QualityReportNotFoundException) {
            $io->note('No QualityReport produced (post-save pipeline may have skipped scoring).');

            return;
        }

        $reason = $report->decisionReason;
        $io->section('Post-save pipeline (QualityReport)');
        $io->table(['Field', 'Value'], [
            ['Decision', $report->decision->value],
            ['Overall score', \sprintf('%.2f', $report->overallScore)],
            ['Reason (en)', null !== $reason ? $reason->en : '-'],
            ['Reason (fr)', null !== $reason ? $reason->fr : '-'],
        ]);
    }

    private function resolveSource(?string $sourceId, WatchFile $watchFile, SymfonyStyle $io): Source
    {
        if (\is_string($sourceId)) {
            $source = $this->sourceGateway->get($sourceId);
            // Echo via $io->writeln (not logger) so `--source-id` callers
            // and tests still see this confirmation regardless of -v level.
            $io->writeln(\sprintf('<info>Using source:</info> %s', $source->getName()));

            return $source;
        }

        foreach ($watchFile->getSources() as $source) {
            if (SourceType::MANUAL === $source->getType()) {
                $this->logger->info('Using existing manual source: {name}', [
                    'name' => $source->getName(),
                ]);

                return $source;
            }
        }

        $source = $this->manualSourceFactory->buildFor($watchFile);
        $this->sourceGateway->save($source);
        $io->writeln('<info>Created new manual source.</info>');

        return $source;
    }
}
