<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Web;

use App\Application\Collect\Web\Exception\InvalidWebCollectConfigException;
use App\Application\Collect\Web\FetchWebUrlAction;
use App\Application\Collect\Web\WebCollectConfig;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\ProviderGatewayInterface;
use App\Domain\Collect\ValueObject\Collector;
use App\Domain\Source\SourceType;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Inline web provider — backs `SourceType::MANUAL` (and any other type that
 * routes to `web` in `app.collect.provider_routing.source_types`).
 *
 * Unlike Apify/Bakus which schedule remote runs, the `web` provider performs
 * the entire ingestion inside the local process: `createTask()` validates the
 * payload (URL or raw HTML in the CollectTask `configuration`) and dispatches
 * a {@see FetchWebUrlAction} that does the fetch + extract + Document build
 * inline (the action implements {@see SyncActionInterface}).
 *
 * Dispatching uses {@see DispatchAfterCurrentBusStamp} so the work runs AFTER
 * the parent {@see CreateCollectTaskHandler} has finished its own state
 * machine (start → save) — without this, the FetchWebUrlHandler would try
 * to `resume()` a CollectTask still in CREATED state and trip the transition
 * machine.
 *
 * Sync chain propagation: the CLI `--sync` path stores `_sync_chain: true`
 * in the CollectTask configuration via {@see CreateCollectTaskHandler}. We
 * read that hint here and forward it to the FetchWebUrlAction so the
 * downstream IngestDocumentAction (and the post-save pipeline) also pin to
 * the `sync` transport — the operator gets a coherent post-pipeline view in
 * the recap.
 */
readonly class WebProviderGateway implements ProviderGatewayInterface
{
    private const string PROVIDER_NAME = 'web';

    public function __construct(
        private MessageBusInterface $messageBus,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function createTask(CollectTask $collectTask): string
    {
        try {
            $config = WebCollectConfig::fromCollectTaskConfiguration($collectTask->getConfiguration());
            $config->validate();
        } catch (InvalidWebCollectConfigException $e) {
            throw new CollectException($e->getMessage(), 0, $e);
        }

        $collectTaskId = $collectTask->getId();
        if (null === $collectTaskId) {
            // CreateCollectTaskHandler saves the task before calling us, so
            // this branch is essentially unreachable in production. The
            // explicit guard keeps the type system honest and surfaces the
            // logic violation if a future caller forgets the save.
            throw new CollectException('Web provider received an unsaved CollectTask without an id.');
        }

        // Synthetic id, opaque to callers — there is no remote run to track.
        // Kept distinguishable via the `web-` prefix so logs and audit
        // dashboards can spot the provider at a glance.
        $providerTaskId = 'web-' . bin2hex(random_bytes(8));

        $this->messageBus->dispatch(
            new FetchWebUrlAction(collectTaskId: $collectTaskId, sync: $config->syncChain),
            [new DispatchAfterCurrentBusStamp()],
        );

        $this->logger?->info('CollectTask created with web provider', [
            'collect_task_id' => $collectTaskId,
            'provider_task_id' => $providerTaskId,
            'has_url' => null !== $config->url,
            'has_raw_html' => null !== $config->rawHtml,
            'sync_chain' => $config->syncChain,
        ]);

        return $providerTaskId;
    }

    public function cancelTask(string $taskId): void
    {
        // No-op: the inline work either runs to completion or fails — there
        // is no remote handle to abort. A task in QUEUED state simply has
        // its deferred FetchWebUrlAction sitting on the bus until processed.
        $this->logger?->info('Web provider cancelTask is a no-op', [
            'provider_task_id' => $taskId,
        ]);
    }

    public function getTaskStatus(string $taskId): CollectTaskStatus
    {
        // The CollectTask state machine itself is the authority — the gateway
        // has no remote API to poll. Returning QUEUED matches what
        // CreateCollectTaskHandler has just transitioned to via `start()` so
        // the handler's "diff and dispatch UpdateTaskStatusAction" branch
        // does not fire spuriously. Once FetchWebUrlHandler has run (after
        // the current bus completes), the CollectTask is COMPLETED in DB —
        // callers re-load via the gateway to observe it.
        return CollectTaskStatus::QUEUED;
    }

    /**
     * @return list<Collector>
     */
    public function getCollectors(): array
    {
        return [
            new Collector(
                name: self::PROVIDER_NAME,
                displayName: [
                    'en' => 'Web fetch / manual paste',
                ],
                description: [
                    'en' => 'Inline ingestion of a single URL (HTTP fetch via HtmlFetcher) or pasted raw HTML.',
                ],
                type: self::PROVIDER_NAME,
                version: '1.0.0',
                iconUrl: '',
                parameters: [],
                returnTypes: [],
                supportStream: false,
                supportBatch: false,
                supportedSourceTypes: [SourceType::MANUAL],
            ),
        ];
    }
}
