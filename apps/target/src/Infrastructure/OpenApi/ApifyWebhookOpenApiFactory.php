<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Registers the /api/apify/webhook endpoint in the OpenAPI schema and marks
 * it as public (x-public: true) so the global-service gateway skips JWT auth.
 *
 * The endpoint uses its own authentication mechanism (JWT query parameter with
 * collect_task_id claim) — not Keycloak — because Apify cannot attach a
 * Keycloak token to its outgoing webhook callbacks.
 */
final readonly class ApifyWebhookOpenApiFactory implements OpenApiFactoryInterface
{
    public function __construct(
        private OpenApiFactoryInterface $decorated,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        $paths = $openApi->getPaths();
        $paths->addPath('/api/apify/webhook', $this->buildWebhookPathItem());

        return $openApi->withPaths($paths);
    }

    private function buildWebhookPathItem(): PathItem
    {
        $operation = new Operation(
            operationId: 'apifyWebhook',
            tags: ['Apify'],
            responses: [
                '200' => new Response(description: 'Webhook processed successfully'),
                '400' => new Response(description: 'Invalid payload'),
                '401' => new Response(description: 'Invalid or missing JWT token'),
            ],
            summary: 'Receive Apify actor run webhook',
            description: 'Called by Apify when an actor run completes. Authenticated via JWT query parameter (collect_task_id claim), not Keycloak.',
            parameters: [
                new Parameter(
                    name: 'token',
                    in: 'query',
                    description: 'JWT token containing collect_task_id claim',
                    required: true,
                    schema: [
                        'type' => 'string',
                    ],
                ),
            ],
            requestBody: new RequestBody(
                description: 'Apify webhook payload',
                content: new \ArrayObject([
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'required' => ['eventType', 'resource'],
                            'properties' => [
                                'eventType' => [
                                    'type' => 'string',
                                    'example' => 'ACTOR.RUN.SUCCEEDED',
                                ],
                                'resource' => [
                                    'type' => 'object',
                                    'required' => ['status'],
                                    'properties' => [
                                        'status' => [
                                            'type' => 'string',
                                        ],
                                        'defaultDatasetId' => [
                                            'type' => 'string',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]),
            ),
            extensionProperties: [
                'x-public' => true,
            ],
        );

        return new PathItem(post: $operation);
    }
}
