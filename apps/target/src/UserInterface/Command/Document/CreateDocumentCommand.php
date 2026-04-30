<?php

declare(strict_types=1);

namespace App\UserInterface\Command\Document;

use App\Application\Document\IngestDocumentAction;
use App\Application\Document\IngestDocumentHandler;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Document\DocumentBuilderFromHtmlMetadata;
use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Document\HtmlFetchException;
use App\Domain\Document\HtmlMetadataExtractor;
use App\Domain\Source\ManualSourceFactory;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'document:create', description: 'Create a document manually from a URL or HTML file',)]
final class CreateDocumentCommand extends Command
{
    private const string PROVIDER_CLOUDFLARE = 'cloudflare';
    private const string PROVIDER_MANUAL = 'manual';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly IngestDocumentHandler $ingestDocumentHandler,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly SourceGatewayInterface $sourceGateway,
        private readonly CollectTaskGatewayInterface $collectTaskGateway,
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
                'Process document synchronously instead of dispatching to message bus'
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
            ['Excerpt', mb_substr($previewExcerpt, 0, 80) . (mb_strlen($previewExcerpt) > 80 ? '...' : '')],
            ['Language', $metadata->language],
            ['Date', $previewDate->format('Y-m-d H:i:s')],
            ['Author', $metadata->author ?? '-'],
            ['Site', $metadata->siteName ?? '-'],
            ['Image', $metadata->imageUrl ? 'yes' : '-'],
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

        // Create CollectTask
        $collectTask = new CollectTask(source: $source, watchFile: $watchFile, providerName: $providerName);
        $collectTask->start('cli-' . bin2hex(random_bytes(8)), $this->eventDispatcher);
        $collectTask->resume($this->eventDispatcher);
        $collectTask->complete($this->eventDispatcher);
        $this->collectTaskGateway->save($collectTask);

        $document = $this->documentBuilder->build(
            metadata: $metadata,
            rawHtml: $html,
            sourceUrl: $url,
            titleOverride: $titleOverrideValue,
            excerptOverride: $excerptOverrideValue,
        );

        $collectTaskId = $collectTask->getId();
        \assert(\is_string($collectTaskId));

        $action = new IngestDocumentAction($collectTaskId, $document);
        $sync = $input->getOption('sync');

        if ($sync) {
            $result = ($this->ingestDocumentHandler)($action);
            $io->success(\sprintf('Document created: %s (ID: %s)', $result->getTitle(), $result->getId()));
        } else {
            $this->messageBus->dispatch($action);
            $io->success(\sprintf('Document dispatched for creation: %s', $document->getTitle()));
        }

        return Command::SUCCESS;
    }

    private function resolveSource(
        ?string $sourceId,
        ?string $url,
        \App\Domain\WatchFile\WatchFile $watchFile,
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
