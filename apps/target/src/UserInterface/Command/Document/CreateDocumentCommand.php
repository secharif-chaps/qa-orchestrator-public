<?php

declare(strict_types=1);

namespace App\UserInterface\Command\Document;

use App\Application\Document\IngestDocumentAction;
use App\Application\Document\IngestDocumentResult;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentBuilderFromHtmlMetadata;
use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Document\HtmlFetchException;
use App\Domain\Document\HtmlMetadataExtractor;
use App\Domain\DocumentQuality\Exception\QualityReportNotFoundException;
use App\Domain\DocumentQuality\QualityReportGatewayInterface;
use App\Domain\Source\ManualSourceFactory;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Manual document creation from the CLI — fetches a URL (or reads a local
 * HTML file), extracts metadata, builds a {@see Document}, persists a
 * synthetic {@see CollectTask} and dispatches the document through the
 * standard {@see IngestDocumentAction} flow.
 *
 * Operational uses:
 * - On-call ingestion of a single URL when a provider misses it.
 * - Reproducing a flaky pipeline run from a saved HTML file.
 * - Smoke-testing a watch_file's pre/post-save processors end-to-end.
 *
 * `--sync` forces the entire pipeline (pre-save + persistence + post-save)
 * to complete before the command returns, so the caller can inspect the
 * resulting document immediately. Without `--sync` only the pre-save
 * pipeline runs inline; the post-save pipeline is dispatched async.
 */
#[AsCommand(name: 'document:create', description: 'Create a document manually from a URL or HTML file')]
class CreateDocumentCommand extends Command
{
    private const string PROVIDER_CLOUDFLARE = 'cloudflare';
    private const string PROVIDER_MANUAL = 'manual';

    /**
     * Hard ceiling for excerpt previews printed in the recap table —
     * keeps the terminal output readable on long teasers.
     */
    private const int EXCERPT_PREVIEW_MAX_LENGTH = 80;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly SourceGatewayInterface $sourceGateway,
        private readonly CollectTaskGatewayInterface $collectTaskGateway,
        private readonly QualityReportGatewayInterface $qualityReportGateway,
        private readonly HtmlFetcherInterface $htmlFetcher,
        private readonly HtmlMetadataExtractor $metadataExtractor,
        private readonly DocumentBuilderFromHtmlMetadata $documentBuilder,
        private readonly ManualSourceFactory $manualSourceFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                Build a Document from a URL or local HTML file and dispatch
                it through the standard ingestion pipeline.

                <info>Async (default)</info>: dispatches the message to the
                bus and returns immediately. Pre-save and post-save
                pipelines run on workers.

                <info>--sync</info>: forces both the ingestion and the
                post-save pipeline onto the in-memory <comment>sync</comment>
                transport. The command waits for everything to complete
                and prints a recap (pipeline signals, dedup verdict,
                quality report) before exiting. Useful for one-off
                ingests, debug, and integration tests.

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
                'Run the full pipeline (pre-save + post-save) on the sync transport and print a recap'
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
        $sourceId = $input->getOption('source-id');

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

        $io->info(\sprintf('WatchFile: %s (%s)', $watchFile->getName(), $watchFileId));

        // Resolve source
        $source = $this->resolveSource($sourceId, $url, $watchFile, $io);

        // Resolve HTML content
        $providerName = null !== $url ? self::PROVIDER_CLOUDFLARE : self::PROVIDER_MANUAL;
        $html = $this->resolveHtml($url, $htmlFile, $io);
        if (null === $html) {
            return Command::FAILURE;
        }

        $io->info('Extracting metadata...');
        $metadata = $this->metadataExtractor->extract($html, \is_string($url) ? $url : null);

        $titleOverrideValue = \is_string($titleOverride) ? $titleOverride : null;
        $excerptOverrideValue = \is_string($excerptOverride) ? $excerptOverride : null;

        $previewTitle = $titleOverrideValue ?? $metadata->title;
        $previewExcerpt = $excerptOverrideValue ?? $metadata->excerpt;
        $previewDate = $metadata->datePublish ?? new \DateTimeImmutable();

        $io->table(['Field', 'Value'], [
            ['Title', $previewTitle],
            ['Excerpt', $this->truncate($previewExcerpt, self::EXCERPT_PREVIEW_MAX_LENGTH)],
            ['Language', $metadata->language],
            ['Date', $previewDate->format('Y-m-d H:i:s')],
            ['Author', $metadata->author ?? '-'],
            ['Site', $metadata->siteName ?? '-'],
            ['Image', null !== $metadata->imageUrl ? 'yes' : '-'],
            ['Canonical', $metadata->canonicalUrl ?? '-'],
            ['Content length', number_format(\strlen($metadata->content)) . ' chars'],
            ['Provider', $providerName],
        ]);

        // Validate content quality
        $contentIssue = $metadata->getContentIssue();
        if (null !== $contentIssue) {
            $io->error(\sprintf('Content rejected: %s', $contentIssue));

            return Command::FAILURE;
        }

        $collectTask = $this->buildCollectTask($source, $watchFile, $providerName);
        $document = $this->documentBuilder->build(
            metadata: $metadata,
            rawHtml: $html,
            sourceUrl: $url,
            titleOverride: $titleOverrideValue,
            excerptOverride: $excerptOverrideValue,
        );

        $collectTaskId = $collectTask->getId();
        \assert(\is_string($collectTaskId));

        $sync = true === $input->getOption('sync');

        if (!$sync) {
            $this->messageBus->dispatch(new IngestDocumentAction($collectTaskId, $document));
            $io->success(\sprintf('Document dispatched for async ingestion: %s', $document->getTitle()));

            return Command::SUCCESS;
        }

        $envelope = $this->messageBus->dispatch(
            new IngestDocumentAction(collectTaskId: $collectTaskId, document: $document, sync: true),
            [new TransportNamesStamp(['sync'])],
        );
        $result = $this->extractIngestResult($envelope);
        $this->renderSyncRecap($io, $result);

        return Command::SUCCESS;
    }

    private function buildCollectTask(Source $source, WatchFile $watchFile, string $providerName): CollectTask
    {
        $task = new CollectTask(source: $source, watchFile: $watchFile, providerName: $providerName);
        // CLI ingestion has no real provider job id — synthesise a tagged
        // identifier so the task is greppable in audit logs.
        $task->start('cli-' . bin2hex(random_bytes(8)), $this->eventDispatcher);
        $task->resume($this->eventDispatcher);
        $task->complete($this->eventDispatcher);
        $this->collectTaskGateway->save($task);

        return $task;
    }

    private function extractIngestResult(Envelope $envelope): IngestDocumentResult
    {
        $stamp = $envelope->last(HandledStamp::class);
        if (!$stamp instanceof HandledStamp) {
            throw new \LogicException(
                'IngestDocumentAction was dispatched on the sync transport but no HandledStamp came back — check that IngestDocumentHandler is registered as a handler for this message.',
            );
        }

        $result = $stamp->getResult();
        if (!$result instanceof IngestDocumentResult) {
            throw new \LogicException(\sprintf(
                'IngestDocumentHandler must return an IngestDocumentResult; got "%s".',
                get_debug_type($result),
            ));
        }

        return $result;
    }

    private function renderSyncRecap(SymfonyStyle $io, IngestDocumentResult $result): void
    {
        $document = $result->document;

        if ($result->isDuplicate()) {
            $io->warning(\sprintf(
                'Document REJECTED as duplicate of "%s" (no save performed).',
                $result->duplicateOf() ?? 'unknown',
            ));
        } else {
            $io->success(\sprintf(
                'Document persisted: "%s" (ID: %s).',
                $document->getTitle(),
                $document->getId(),
            ));
        }

        $this->renderPipelineSignals($io, $result);
        $this->renderQualityReport($io, $document);
    }

    private function renderPipelineSignals(SymfonyStyle $io, IngestDocumentResult $result): void
    {
        $context = $result->preSaveContext;
        if (null === $context) {
            $io->note('Pre-save pipeline was skipped (document had no associated WatchFile).');

            return;
        }

        $io->section('Pre-save pipeline');

        $haltReason = $context->haltReason;
        $rows = [
            ['Halted', $context->isHalted ? 'yes' : 'no'],
            ['Halt reason', null !== $haltReason ? $haltReason->en : '-'],
            ['Duplicate of', $context->duplicateOf ?? '-'],
            ['Canonical URL', $context->canonicalUrl ?? '-'],
        ];

        if ([] === $context->signals) {
            $rows[] = ['Signals', '(none)'];
        }

        $io->table(['Field', 'Value'], $rows);

        if ([] !== $context->signals) {
            $signalRows = [];
            foreach ($context->signals as $name => $signal) {
                $signalRows[] = [
                    $name,
                    \sprintf('%.3f', $signal->value),
                    \sprintf('%.2f', $signal->weight),
                    \sprintf('%.3f', $signal->contribution()),
                    $signal->category->value,
                ];
            }
            $io->table(['Signal', 'Value', 'Weight', 'Contribution', 'Category'], $signalRows);
        }
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

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max) . '...';
    }

    private function resolveSource(
        ?string $sourceId,
        ?string $url,
        WatchFile $watchFile,
        SymfonyStyle $io,
    ): Source {
        if (\is_string($sourceId)) {
            $source = $this->sourceGateway->get($sourceId);
            $io->info(\sprintf('Using source: %s', $source->getName()));

            return $source;
        }

        foreach ($watchFile->getSources() as $source) {
            if (SourceType::MANUAL === $source->getType()) {
                $io->info(\sprintf('Using existing manual source: %s', $source->getName()));

                return $source;
            }
        }

        $source = $this->manualSourceFactory->buildFor($watchFile);
        $this->sourceGateway->save($source);
        $io->info('Created new manual source.');

        return $source;
    }

    private function resolveHtml(?string $url, ?string $htmlFile, SymfonyStyle $io): ?string
    {
        if (null !== $url) {
            $io->info(\sprintf('Fetching URL: %s', $url));

            try {
                return $this->htmlFetcher->fetch($url);
            } catch (HtmlFetchException $e) {
                $io->error(\sprintf('Failed to fetch URL: %s', $e->getMessage()));

                return null;
            }
        }

        if (!\is_string($htmlFile) || !file_exists($htmlFile)) {
            $io->error(\sprintf('File not found: %s', $htmlFile));

            return null;
        }

        $html = file_get_contents($htmlFile);
        if (false === $html || '' === $html) {
            $io->error(\sprintf('Failed to read or empty file: %s', $htmlFile));

            return null;
        }

        $io->info(\sprintf('Read HTML file: %s (%s bytes)', $htmlFile, number_format(\strlen($html))));

        return $html;
    }
}
