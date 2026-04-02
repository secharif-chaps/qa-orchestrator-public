<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\Actor;
use App\Domain\Source\Source;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Utils\OpenApiSchemaLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\HttpClient\ResponseInterface;

class OpenApiValidationTest extends AbstractApiTestCase
{
    /** @var array{paths: array<string, array<string, mixed>>, components: array{schemas: array<string, mixed>}} */
    private static array $openApiSchema;
    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        if (!isset(self::$openApiSchema)) {
            self::$openApiSchema = OpenApiSchemaLoader::loadSchema(self::bootKernel());
        }

        $this->testUser = UserFactory::new()
            ->defaultBasilUser()
            ->create();
    }

    /**
     * @param array<string, mixed> $operation
     */
    #[DataProvider('getEndpointsProvider')]
    public function testEndpointReturnsValidResponse(string $method, string $path, array $operation): void
    {
        if ($this->shouldSkipEndpoint($path, $method)) {
            $this->markTestSkipped(\sprintf('Skipping complex endpoint: %s %s', $method, $path));
        }

        $this->assertNotEmpty(
            $operation['summary'] ?? '',
            \sprintf('Operation summary should not be empty for %s %s', $method, $path),
        );

        $this->assertNotEmpty(
            $operation['description'] ?? '',
            \sprintf('Operation description should not be empty for %s %s', $method, $path),
        );

        $resolvedPath = $this->resolvePathParameters($path);
        if (null === $resolvedPath) {
            $this->markTestSkipped(\sprintf('Could not resolve path parameters for: %s', $path));
        }

        $client = $this->createAuthenticatedClient($this->testUser);
        $response = $client->request($method, $resolvedPath);

        $statusCode = $response->getStatusCode();

        // Accept both success and not found as valid responses
        $this->assertContains($statusCode, [200, 201, 204, 404],
            \sprintf('Unexpected status code %d for %s %s', $statusCode, $method, $resolvedPath)
        );

        // Only validate successful responses
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->validateResponseStructure($path, $method, $statusCode, $response);
        }
    }

    /** @return \Generator<string, array{string, string, array<string, mixed>}> */
    public static function getEndpointsProvider(): \Generator
    {
        $schema = OpenApiSchemaLoader::loadSchema(self::bootKernel());

        foreach (OpenApiSchemaLoader::getEndpoints($schema, ['GET']) as $endpoint) {
            /** @var array{method: string, path: string, operation: array<string, mixed>} $endpoint */
            yield \sprintf('%s %s', $endpoint['method'], $endpoint['path']) => [
                $endpoint['method'],
                $endpoint['path'],
                $endpoint['operation'],
            ];
        }
    }

    private function shouldSkipEndpoint(string $path, string $method): bool
    {
        if ('GET' !== $method) {
            return true;
        }

        // Skip documents endpoints for now
        if (str_contains($path, '/documents')) {
            return true;
        }

        return false;
    }

    private function resolvePathParameters(string $path): ?string
    {
        // Replace path parameters with actual IDs from test entities
        if (preg_match_all('/\{(\w+)\}/', $path, $matches)) {
            $replacements = [];

            foreach ($matches[1] as $paramName) {
                $id = $this->getTestEntityId($paramName);
                if (null === $id) {
                    return null;
                }
                $replacements["{{$paramName}}"] = $id;
            }

            return str_replace(array_keys($replacements), array_values($replacements), $path);
        }

        return $path;
    }

    private function getTestEntityId(string $paramName): ?string
    {
        return match ($paramName) {
            'watchFileId', 'id' => $this
                ->createTestWatchFile()
                ->getId(),
            'userId' => $this
                ->testUser
                ->getId(),
            'actorId' => $this
                ->createTestActor()
                ->getId(),
            'sourceId' => $this
                ->createTestSource()
                ->getId(),
            'conversationId' => null, // Skip conversation endpoints for now
            default => null,
        };
    }

    private function createTestWatchFile(): WatchFile
    {
        return WatchFileFactory::new()
            ->withCreatedBy($this->testUser)
            ->withOwnedBy($this->testUser)
            ->create();
    }

    private function createTestActor(): Actor
    {
        $actor = ActorFactory::new();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($this->testUser)
            ->withOwnedBy($this->testUser);

        WatchFileActorFactory::new()
            ->with([
                'actor' => $actor,
                'watchFile' => $watchFile,
            ]);

        return $actor->create();
    }

    private function createTestSource(): Source
    {
        $watchFile = $this->createTestWatchFile();

        return SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();
    }

    private function validateResponseStructure(
        string $path,
        string $method,
        int $statusCode,
        ResponseInterface $response,
    ): void {
        // Check Content-Type header
        $this->assertResponseHasHeader('Content-Type');
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('content-type', $headers);
        $this->assertArrayHasKey(0, $headers['content-type']);

        $contentType = $headers['content-type'][0];
        $this->assertNotEmpty($contentType, 'Response should have Content-Type header');
        $this->assertStringStartsWith('application/', $contentType, 'Response should be application/* content type');

        // For JSON responses, validate structure
        if (str_contains($contentType, 'json')) {
            $responseData = json_decode($response->getContent(), true);
            $this->assertIsArray($responseData, 'JSON response should be valid JSON');

            // Validate against OpenAPI schema
            $errors = OpenApiSchemaLoader::validateResponse(
                self::$openApiSchema,
                $path,
                strtolower($method),
                $statusCode,
                $responseData
            );

            if (!empty($errors)) {
                $this->fail(\sprintf(
                    "Response validation failed for %s %s:\n%s\nResponse: %s",
                    $method,
                    $path,
                    implode("\n", $errors),
                    json_encode($responseData, \JSON_PRETTY_PRINT)
                ));
            }

            // Basic API Platform JSON-LD validation for collection responses
            if (isset($responseData['@context']) && \is_string($responseData['@context']) && str_contains(
                $responseData['@context'],
                'hydra'
            )) {
                $this->assertArrayHasKey('@type', $responseData, 'Hydra response should have @type');

                if ('hydra:Collection' === $responseData['@type']) {
                    $this->assertArrayHasKey('member', $responseData, 'Collection should have member property');
                    $this->assertIsArray($responseData['member'], 'Collection member should be array');
                }
            }

            // Validate UUID fields if present
            $this->validateUuidFields($responseData);
        }
    }

    /** @param array<string, mixed> $data */
    private function validateUuidFields(array $data, string $path = ''): void
    {
        foreach ($data as $key => $value) {
            $currentPath = $path ? "{$path}.{$key}" : $key;

            // Check if this looks like an ID field
            if ((str_ends_with($key, 'Id') || 'id' === $key) && \is_string($value)) {
                $this->assertMatchesRegularExpression(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                    $value,
                    \sprintf('Field %s should be a valid UUID', $currentPath)
                );
            }

            // Recursive validation for nested objects/arrays
            if (\is_array($value)) {
                if ($this->isAssociativeArray($value)) {
                    /** @var array<string, mixed> $value */
                    $this->validateUuidFields($value, $currentPath);
                } else {
                    foreach ($value as $index => $item) {
                        if (\is_array($item)) {
                            /** @var array<string, mixed> $item */
                            $this->validateUuidFields($item, "{$currentPath}[{$index}]");
                        }
                    }
                }
            }
        }
    }

    /** @param array<mixed, mixed> $array */
    private function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, \count($array) - 1);
    }
}
