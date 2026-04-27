<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Http;

use App\Application\Collect\Apify\FetchApifyDatasetAction;
use App\Application\Collect\Task\UpdateTaskStatusAction;
use App\Domain\Collect\ApifyRunCost;
use App\Domain\Collect\CollectTaskStatus;
use App\Infrastructure\Collect\Apify\ApifyStatusMapper;
use App\Tests\Utils\Symfony\NullMessageBus;
use App\UserInterface\Http\ApifyWebhookController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[CoversClass(ApifyWebhookController::class)]
class ApifyWebhookControllerTest extends TestCase
{
    private ApifyStatusMapper $statusMapper;

    protected function setUp(): void
    {
        $this->statusMapper = new ApifyStatusMapper();
    }

    /**
     * HandleTrait::handle() calls $messageBus->dispatch() and reads the result
     * from a HandledStamp. NullMessageBus adds that stamp automatically when
     * a fakeHandler is provided.
     */
    private function createMessageBus(): NullMessageBus
    {
        return new NullMessageBus(fakeHandler: fn () => null);
    }

    public function testValidWebhookDispatchesFetchDatasetAction(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-123',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'id' => 'run123',
                    'status' => 'SUCCEEDED',
                    'defaultDatasetId' => 'dataset456',
                ],
            ],
        );

        $response = ($controller)($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"status":"ok"}', $response->getContent());

        $action = $messageBus->getFirstDispatched(FetchApifyDatasetAction::class);
        $this->assertInstanceOf(FetchApifyDatasetAction::class, $action);
        $this->assertSame('task-uuid-123', $action->collectTaskId);
        $this->assertSame('dataset456', $action->datasetId);
        $this->assertSame(1, $messageBus->countDispatched(FetchApifyDatasetAction::class));
        $this->assertSame(0, $messageBus->countDispatched(UpdateTaskStatusAction::class));
    }

    public function testWebhookWithAbortedStatus(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-789',
            payload: [
                'eventType' => 'ACTOR.RUN.ABORTED',
                'resource' => [
                    'id' => 'run000',
                    'status' => 'ABORTED',
                ],
            ],
        );

        $response = ($controller)($request);

        $this->assertSame(200, $response->getStatusCode());
        $action = $messageBus->getFirstDispatched(UpdateTaskStatusAction::class);
        $this->assertInstanceOf(UpdateTaskStatusAction::class, $action);
        $this->assertSame(CollectTaskStatus::CANCELLED, $action->status);
    }

    public function testWebhookMissingCollectTaskIdThrows(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing collect_task_id from authentication');

        $controller = new ApifyWebhookController($this->statusMapper, new NullMessageBus());

        $request = Request::create('/api/apify/webhook', 'POST', [], [], [], [], json_encode([
            'eventType' => 'ACTOR.RUN.SUCCEEDED',
            'resource' => [
                'status' => 'SUCCEEDED',
            ],
        ], \JSON_THROW_ON_ERROR));
        $request->headers->set('Content-Type', 'application/json');

        ($controller)($request);
    }

    public function testWebhookEmptyBodyThrows(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Empty request body');

        $controller = new ApifyWebhookController($this->statusMapper, new NullMessageBus());

        $request = Request::create('/api/apify/webhook', 'POST');
        $request->attributes->set('collect_task_id', 'task-123');

        ($controller)($request);
    }

    public function testWebhookInvalidJsonThrows(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON payload');

        $controller = new ApifyWebhookController($this->statusMapper, new NullMessageBus());

        $request = Request::create('/api/apify/webhook', 'POST', [], [], [], [], 'not-json');
        $request->attributes->set('collect_task_id', 'task-123');

        ($controller)($request);
    }

    public function testWebhookMissingResourceStatusThrows(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing or invalid "resource.status" in webhook payload');

        $controller = new ApifyWebhookController($this->statusMapper, new NullMessageBus());

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-123',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'id' => 'run123',
                ],
            ],
        );

        ($controller)($request);
    }

    public function testWebhookSucceededWithoutDatasetIdThrows(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing or invalid "resource.defaultDatasetId" in webhook payload');

        $controller = new ApifyWebhookController($this->statusMapper, new NullMessageBus());

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-123',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'id' => 'run123',
                    'status' => 'SUCCEEDED',
                ],
            ],
        );

        ($controller)($request);
    }

    public function testFailedStatusDispatchesUpdateAction(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-456',
            payload: [
                'eventType' => 'ACTOR.RUN.FAILED',
                'resource' => [
                    'id' => 'run789',
                    'status' => 'FAILED',
                ],
            ],
        );

        $response = ($controller)($request);

        $this->assertSame(200, $response->getStatusCode());
        $action = $messageBus->getFirstDispatched(UpdateTaskStatusAction::class);
        $this->assertInstanceOf(UpdateTaskStatusAction::class, $action);
        $this->assertSame('task-uuid-456', $action->collectTaskId);
        $this->assertSame(CollectTaskStatus::FAILED, $action->status);
        $this->assertSame(1, $messageBus->countDispatched(UpdateTaskStatusAction::class));
        $this->assertSame(0, $messageBus->countDispatched(FetchApifyDatasetAction::class));
    }

    public function testFetchActionContainsRunCostWhenUsagePresent(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-cost',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'status' => 'SUCCEEDED',
                    'defaultDatasetId' => 'dataset_cost',
                    'usage' => [
                        'ACTOR_COMPUTE_UNITS' => 2.5,
                    ],
                    'usageUsd' => [
                        'ACTOR_COMPUTE_UNITS' => 0.005,
                    ],
                    'startedAt' => '2024-01-01T10:00:00.000Z',
                    'finishedAt' => '2024-01-01T10:01:00.000Z',
                ],
            ],
        );

        ($controller)($request);

        $action = $messageBus->getFirstDispatched(FetchApifyDatasetAction::class);
        $this->assertInstanceOf(FetchApifyDatasetAction::class, $action);
        $this->assertInstanceOf(ApifyRunCost::class, $action->runCost);
        $this->assertSame(2.5, $action->runCost->computeUnits);
        $this->assertSame(0.005, $action->runCost->costUsd);
        $this->assertSame(60, $action->runCost->durationSeconds);
    }

    public function testFetchActionHasNullRunCostWhenUsageAbsent(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-no-cost',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'status' => 'SUCCEEDED',
                    'defaultDatasetId' => 'dataset_no_cost',
                ],
            ],
        );

        ($controller)($request);

        $action = $messageBus->getFirstDispatched(FetchApifyDatasetAction::class);
        $this->assertInstanceOf(FetchApifyDatasetAction::class, $action);
        $this->assertNull($action->runCost);
    }

    public function testRunCostDefaultsToZeroForMissingUsageKeys(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-empty-usage',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'status' => 'SUCCEEDED',
                    'defaultDatasetId' => 'dataset_empty_usage',
                    'usage' => [],
                    'usageUsd' => [],
                ],
            ],
        );

        ($controller)($request);

        $action = $messageBus->getFirstDispatched(FetchApifyDatasetAction::class);
        $this->assertInstanceOf(FetchApifyDatasetAction::class, $action);
        $this->assertInstanceOf(ApifyRunCost::class, $action->runCost);
        $this->assertSame(0.0, $action->runCost->computeUnits);
        $this->assertSame(0.0, $action->runCost->costUsd);
        $this->assertNull($action->runCost->durationSeconds);
    }

    public function testRunCostIgnoresInvalidDates(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-bad-dates',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'status' => 'SUCCEEDED',
                    'defaultDatasetId' => 'dataset_bad_dates',
                    'usage' => [
                        'ACTOR_COMPUTE_UNITS' => 1.0,
                    ],
                    'usageUsd' => [
                        'ACTOR_COMPUTE_UNITS' => 0.002,
                    ],
                    'startedAt' => 'not-a-date',
                    'finishedAt' => 'also-not-a-date',
                ],
            ],
        );

        ($controller)($request);

        $action = $messageBus->getFirstDispatched(FetchApifyDatasetAction::class);
        $this->assertInstanceOf(FetchApifyDatasetAction::class, $action);
        $this->assertInstanceOf(ApifyRunCost::class, $action->runCost);
        $this->assertNull($action->runCost->durationSeconds);
    }

    public function testRunCostHandlesIntegerUsageValues(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-int-usage',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'status' => 'SUCCEEDED',
                    'defaultDatasetId' => 'dataset_int_usage',
                    'usage' => [
                        'ACTOR_COMPUTE_UNITS' => 2,
                    ],
                    'usageUsd' => [
                        'ACTOR_COMPUTE_UNITS' => 0,
                    ],
                ],
            ],
        );

        ($controller)($request);

        $action = $messageBus->getFirstDispatched(FetchApifyDatasetAction::class);
        $this->assertInstanceOf(FetchApifyDatasetAction::class, $action);
        $this->assertInstanceOf(ApifyRunCost::class, $action->runCost);
        $this->assertSame(2.0, $action->runCost->computeUnits);
        $this->assertSame(0.0, $action->runCost->costUsd);
    }

    public function testRunCostDefaultsToZeroForNonNumericUsageValues(): void
    {
        $messageBus = $this->createMessageBus();
        $controller = new ApifyWebhookController($this->statusMapper, $messageBus);

        $request = $this->createWebhookRequest(
            collectTaskId: 'task-uuid-invalid-usage',
            payload: [
                'eventType' => 'ACTOR.RUN.SUCCEEDED',
                'resource' => [
                    'status' => 'SUCCEEDED',
                    'defaultDatasetId' => 'dataset_invalid_usage',
                    'usage' => [
                        'ACTOR_COMPUTE_UNITS' => 'invalid',
                    ],
                    'usageUsd' => [
                        'ACTOR_COMPUTE_UNITS' => null,
                    ],
                ],
            ],
        );

        ($controller)($request);

        $action = $messageBus->getFirstDispatched(FetchApifyDatasetAction::class);
        $this->assertInstanceOf(FetchApifyDatasetAction::class, $action);
        $this->assertInstanceOf(ApifyRunCost::class, $action->runCost);
        $this->assertSame(0.0, $action->runCost->computeUnits);
        $this->assertSame(0.0, $action->runCost->costUsd);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createWebhookRequest(string $collectTaskId, array $payload): Request
    {
        $request = Request::create(
            '/api/apify/webhook',
            'POST',
            [],
            [],
            [],
            [],
            json_encode($payload, \JSON_THROW_ON_ERROR),
        );
        $request->headers->set('Content-Type', 'application/json');
        $request->attributes->set('collect_task_id', $collectTaskId);

        return $request;
    }
}
