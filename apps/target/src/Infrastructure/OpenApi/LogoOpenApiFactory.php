<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Parameter;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\OpenApi\OpenApi;

/**
 * Registers the /api/logo/{domain} endpoint in the OpenAPI schema and marks
 * it as public (x-public: true) so the global-service gateway skips JWT auth.
 *
 * The endpoint is defined as a plain Symfony controller (GetLogoController),
 * not an API Platform resource, so it isn't picked up automatically. It must
 * be public because browsers cannot attach an Authorization header to <img>
 * requests — rate limiting is applied per client IP at the controller level.
 */
final readonly class LogoOpenApiFactory implements OpenApiFactoryInterface
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
        $paths->addPath('/api/logo/{domain}', $this->buildLogoPathItem());

        return $openApi->withPaths($paths);
    }

    private function buildLogoPathItem(): PathItem
    {
        $operation = new Operation(
            operationId: 'getCompanyLogo',
            tags: ['Logo'],
            responses: [
                '200' => new Response(description: 'Company logo image'),
                '400' => new Response(description: 'Invalid domain'),
                '404' => new Response(description: 'Logo not found'),
                '429' => new Response(description: 'Rate limit exceeded'),
            ],
            summary: 'Get company logo by domain',
            description: 'Returns the logo image for the given company domain. Public endpoint: <img> tags cannot attach auth headers. Rate-limited per client IP.',
            parameters: [
                new Parameter(
                    name: 'domain',
                    in: 'path',
                    description: 'Company domain (e.g. apple.com)',
                    required: true,
                    schema: [
                        'type' => 'string',
                        'pattern' => '[a-zA-Z0-9\-\.]{3,253}',
                        'example' => 'apple.com',
                    ],
                ),
            ],
            extensionProperties: [
                'x-public' => true,
            ],
        );

        return new PathItem(get: $operation);
    }
}
