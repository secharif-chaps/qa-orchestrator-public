<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\Collect\Task\CreateCollectTaskAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Url\UrlSourceTypeClassifierInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Source\ManualSourceFactory;
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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Webmozart\Assert\Assert;

/**
 * @implements ProcessorInterface<CreateDocumentInputDto, Document>
 *
 * Provider-agnostic API ingestion: dispatches {@see CreateCollectTaskAction}
 * on the in-memory `sync` transport so the orchestrator picks the right
 * provider (web for MANUAL, apify/bakus for structured types) and returns
 * the resulting Document inline. The actual fetch + extract + build work
 * lives in the resolved provider's handler — this processor's only
 * responsibilities are HTTP-level concerns: auth, input validation,
 * exception mapping.
 *
 * Recognised social/video URLs (Twitter, LinkedIn, YouTube, …) are
 * rejected with a 400 when posted against an auto-resolved MANUAL Source —
 * one-shot ingestion of those platforms is not supported yet. Operators
 * who really know what they're doing pass `source_id` to bypass the check.
 */
class CreateDocumentProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly Security $security,
        private readonly WatchFileGatewayInterface $watchFileGateway,
        private readonly SourceGatewayInterface $sourceGateway,
        private readonly DocumentGatewayInterface $documentGateway,
        private readonly ManualSourceFactory $manualSourceFactory,
        private readonly UrlSourceTypeClassifierInterface $urlClassifier,
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
            throw new AccessDeniedHttpException('You do not have permission to create documents in this watchfile.');
        }

        if (null === $inputDto->url && null === $inputDto->html) {
            throw new BadRequestHttpException('You must provide either a "url" or an "html" field.');
        }

        $source = $this->resolveSource($inputDto, $watchFile);

        // URL classification: only enforced on auto-resolved MANUAL sources.
        // An explicit `source_id` is the operator's way to opt out and
        // ingest against a structured Source (e.g. a Twitter feed).
        if (null === $inputDto->sourceId && null !== $inputDto->url && SourceType::MANUAL === $source->getType()) {
            $detected = $this->urlClassifier->classify($inputDto->url);
            if (null !== $detected && SourceType::MANUAL !== $detected) {
                throw new BadRequestHttpException(\sprintf(
                    'URL detected as %s. One-shot ingestion is not yet supported for this platform — create a dedicated Source on the WatchFile and pass its source_id.',
                    $detected->value,
                ));
            }
        }

        $configuration = array_filter(
            [
                'url' => $inputDto->url,
                'raw_html' => $inputDto->html,
                'title' => $inputDto->title,
                'excerpt' => $inputDto->excerpt,
                // The HTTP path runs the chain inline so the response body
                // can carry the persisted Document — same `_sync_chain`
                // marker the CLI uses.
                '_sync_chain' => true,
            ],
            static fn (mixed $value): bool => null !== $value,
        );

        $sourceId = $source->getId();

        $this->logger?->info('Dispatching CreateCollectTaskAction from API', [
            'watch_file_id' => $watchFileId,
            'source_id' => $sourceId,
            'url' => $inputDto->url,
            'has_html' => null !== $inputDto->html,
        ]);

        $envelope = $this->messageBus->dispatch(
            new CreateCollectTaskAction(
                sourceId: $sourceId,
                watchFileId: $watchFileId,
                start: true,
                configuration: $configuration,
            ),
            [new TransportNamesStamp(['sync'])],
        );

        $stamp = $envelope->last(HandledStamp::class);
        if (!$stamp instanceof HandledStamp) {
            throw new UnprocessableEntityHttpException(
                'Collect task dispatch did not return a result — internal orchestration error.',
            );
        }
        $collectTask = $stamp->getResult();
        if (!$collectTask instanceof CollectTask) {
            throw new UnprocessableEntityHttpException('Collect task dispatch returned an unexpected result type.');
        }

        $collectTaskId = $collectTask->getId();
        Assert::stringNotEmpty($collectTaskId);

        $documents = $this->documentGateway->findByCollectTaskId($collectTaskId);
        if ([] === $documents) {
            throw new UnprocessableEntityHttpException(
                'Document was not persisted — pipeline halted (likely a duplicate, an error page, or content too short). Check the worker logs for the halt reason.',
            );
        }

        // The web provider produces exactly one Document per CollectTask.
        // For other providers (would happen only if the operator passed an
        // explicit source_id pointing at a multi-yield Source) we return
        // the first one — the API contract guarantees a single Document
        // response, which is fine for one-shot ingestion in practice.
        return $documents[0];
    }

    private function resolveSource(CreateDocumentInputDto $inputDto, WatchFile $watchFile): Source
    {
        if (null !== $inputDto->sourceId) {
            $source = $this->sourceGateway->get($inputDto->sourceId);
            if ($source->getWatchFile()->getId() !== $watchFile->getId()) {
                throw new AccessDeniedHttpException('Source does not belong to this watchfile.');
            }

            return $source;
        }

        foreach ($watchFile->getSources() as $source) {
            if (SourceType::MANUAL === $source->getType()) {
                return $source;
            }
        }

        $source = $this->manualSourceFactory->buildFor($watchFile);
        $this->sourceGateway->save($source);

        return $source;
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
