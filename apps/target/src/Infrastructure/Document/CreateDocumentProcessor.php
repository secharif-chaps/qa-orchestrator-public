<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Document\AddDocumentAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Document\Document;
use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Document\HtmlFetchException;
use App\Domain\Document\HtmlMetadataExtractor;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\UserInterface\Dto\Document\CreateDocumentInputDto;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreateDocumentInputDto, Document>
 */
class CreateDocumentProcessor implements ProcessorInterface
{
    private const string PROVIDER_CLOUDFLARE = 'cloudflare';
    private const string PROVIDER_MANUAL = 'manual';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Security $security,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly SourceGatewayInterface $sourceGateway,
        private readonly CollectTaskGatewayInterface $collectTaskGateway,
        private readonly HtmlFetcherInterface $htmlFetcher,
        private readonly HtmlMetadataExtractor $metadataExtractor,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): Document {
        $inputDto = $this->extractInputDto($data, $context);

        $watchFileId = $uriVariables['watchFileId'] ?? null;
        Assert::stringNotEmpty($watchFileId, 'WatchFile ID is required.');

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User must be authenticated.');
        }

        $watchFile = $this->watchFileGateway->get($watchFileId);

        if (!$this->security->isGranted(WatchFileVoter::EDIT, $watchFile)) {
            throw new AccessDeniedHttpException('You do not have permission to create documents in this watch file.');
        }

        $source = $this->resolveSource($inputDto, $watchFile);

        $providerName = null !== $inputDto->url ? self::PROVIDER_CLOUDFLARE : self::PROVIDER_MANUAL;
        $html = $this->resolveHtmlContent($inputDto);

        $metadata = $this->metadataExtractor->extract($html, $inputDto->url);

        $contentIssue = $metadata->getContentIssue();
        if (null !== $contentIssue) {
            throw new UnprocessableEntityHttpException(\sprintf('Content rejected: %s', $contentIssue));
        }

        $collectTask = $this->createAndCompleteCollectTask($source, $watchFile, $providerName);

        $title = $inputDto->title ?? $metadata->title;
        $excerpt = $inputDto->excerpt ?? $metadata->excerpt;
        $datePublish = $metadata->datePublish ?? new \DateTimeImmutable();

        $document = new Document(
            id: null,
            title: $title,
            excerpt: $excerpt,
            type: 'html',
            datePublish: $datePublish,
            dateCollect: new \DateTimeImmutable(),
            content: $metadata->content,
            cfcRestricted: false,
            url: $metadata->canonicalUrl ?? $inputDto->url,
        );

        $document->setLanguage($metadata->language);
        $htmlLength = mb_strlen($html);
        $document->setContentRatio($htmlLength > 0 ? mb_strlen($metadata->content) / $htmlLength : null);

        // Use canonical URL for dedup when available (more stable than redirect URLs)
        $dedupUrl = $metadata->canonicalUrl ?? $inputDto->url;
        $providerId = null !== $dedupUrl
            ? hash('sha256', $dedupUrl)
            : hash('sha256', mb_substr($html, 0, 10000));
        $document->setProviderId($providerId);

        $this->logger?->info('Creating manual document', [
            'watch_file_id' => $watchFileId,
            'provider_name' => $providerName,
            'url' => $inputDto->url,
            'title' => $title,
        ]);

        $collectTaskId = $collectTask->getId();
        Assert::stringNotEmpty($collectTaskId);

        $this->messageBus->dispatch(new AddDocumentAction($collectTaskId, $document));

        return $document;
    }

    private function resolveSource(CreateDocumentInputDto $inputDto, WatchFile $watchFile): Source
    {
        if (null !== $inputDto->sourceId) {
            $source = $this->sourceGateway->get($inputDto->sourceId);
            if ($source->getWatchFile()->getId() !== $watchFile->getId()) {
                throw new AccessDeniedHttpException('Source does not belong to this watch file.');
            }

            return $source;
        }

        foreach ($watchFile->getSources() as $source) {
            if (SourceType::MANUAL === $source->getType()) {
                return $source;
            }
        }

        $source = new Source(
            name: 'Manual',
            description: new TranslatedText('Source manuelle', 'Manual source'),
            type: SourceType::MANUAL,
            url: \sprintf('manual://%s', $watchFile->getId()),
            primaryDomain: 'manual',
            relevance: new TranslatedText('Ajout manuel par l\'utilisateur', 'Manually added by user'),
            actor: null,
            watchFile: $watchFile,
        );

        $this->sourceGateway->save($source);

        return $source;
    }

    private function resolveHtmlContent(CreateDocumentInputDto $inputDto): string
    {
        if (null !== $inputDto->url) {
            try {
                return $this->htmlFetcher->fetch($inputDto->url);
            } catch (HtmlFetchException $e) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Failed to fetch URL content: %s',
                    $e->getMessage()
                ), $e, );
            }
        }

        Assert::stringNotEmpty($inputDto->html, 'HTML content must not be empty.');

        return $inputDto->html;
    }

    private function createAndCompleteCollectTask(
        Source $source,
        WatchFile $watchFile,
        string $providerName,
    ): CollectTask {
        $collectTask = new CollectTask(source: $source, watchFile: $watchFile, providerName: $providerName);

        $collectTask->start('manual-' . bin2hex(random_bytes(8)), $this->eventDispatcher);
        $collectTask->resume($this->eventDispatcher);
        $collectTask->complete($this->eventDispatcher);

        $this->collectTaskGateway->save($collectTask);

        return $collectTask;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractInputDto(mixed $data, array $context): CreateDocumentInputDto
    {
        if ($data instanceof CreateDocumentInputDto) {
            return $data;
        }

        /** @var Request|null $request */
        $request = $context['request'] ?? null;
        $payload = [];
        if ($request instanceof Request) {
            $content = (string) $request->getContent();
            $payload = '' !== $content ? (json_decode($content, true) ?: []) : [];
        }

        Assert::isArray($payload, 'Request payload must be an array');

        return new CreateDocumentInputDto(
            url: isset($payload['url']) && \is_string($payload['url']) ? $payload['url'] : null,
            html: isset($payload['html']) && \is_string($payload['html']) ? $payload['html'] : null,
            sourceId: isset($payload['source_id']) && \is_string($payload['source_id']) ? $payload['source_id'] : null,
            title: isset($payload['title']) && \is_string($payload['title']) ? $payload['title'] : null,
            excerpt: isset($payload['excerpt']) && \is_string($payload['excerpt']) ? $payload['excerpt'] : null,
        );
    }
}
