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

class OpenApiCollectionConsistencyTest extends AbstractApiTestCase
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

    #[DataProvider('collectionEndpointsProvider')]
    public function testCollectionItemConsistency(string $collectionPath, string $itemPath): void
    {
        $client = $this->createAuthenticatedClient($this->testUser);

        // 1. Fetch collection
        $collectionResponse = $client->request('GET', $collectionPath);

        if (404 === $collectionResponse->getStatusCode()) {
            $this->markTestSkipped('Collection endpoint not accessible: ' . $collectionPath);
        }

        $this->assertEquals(200, $collectionResponse->getStatusCode());

        $collectionData = json_decode($collectionResponse->getContent(), true);
        $this->assertIsArray($collectionData);
        /** @var array<string, mixed> $collectionData */

        // 2. Check if collection has items, create if empty
        $items = $this->extractCollectionItems($collectionData);
        if (empty($items)) {
            $this->createTestDataBasedOnSchema($collectionPath);

            // Refetch collection after creating test data
            $collectionResponse = $client->request('GET', $collectionPath);
            $this->assertEquals(200, $collectionResponse->getStatusCode());

            $collectionData = json_decode($collectionResponse->getContent(), true);
            $this->assertIsArray($collectionData);
            /** @var array<string, mixed> $collectionData */
            $items = $this->extractCollectionItems($collectionData);

            if (empty($items)) {
                $this->markTestSkipped('Could not create test data for endpoint: ' . $collectionPath);
            }
        }

        // 3. Take the first item from the collection
        $firstItem = $items[0];
        /** @var array<string, mixed> $firstItem */

        // 4. Extract ID and fetch individual item
        $itemId = $this->extractItemId($firstItem);
        if (null === $itemId) {
            $this->fail('Could not extract ID from collection item: ' . json_encode($firstItem));
        }

        $resolvedItemPath = (string) preg_replace('/\{[^}]+\}/', $itemId, $itemPath, 1);
        $itemResponse = $client->request('GET', $resolvedItemPath);
        $this->assertEquals(200, $itemResponse->getStatusCode());

        $itemData = json_decode($itemResponse->getContent(), true);
        $this->assertIsArray($itemData);
        /** @var array<string, mixed> $itemData */

        // 5. Compare common properties
        $this->compareItemProperties($firstItem, $itemData, $collectionPath, $resolvedItemPath);
    }

    /**
     * Auto-discover collection and item endpoint pairs from OpenAPI schema.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function collectionEndpointsProvider(): array
    {
        $schema = OpenApiSchemaLoader::loadSchema(self::bootKernel());
        $endpoints = [];

        foreach ($schema['paths'] as $path => $methods) {
            // Look for GET collection endpoints (no path parameters)
            if (isset($methods['get']) && !str_contains($path, '{') && \is_array($methods['get'])) {
                /** @var array<string, mixed> $operation */
                $operation = $methods['get'];

                // Check if this looks like a collection endpoint
                if (self::isCollectionEndpoint($operation)) {
                    // Try to find the corresponding item endpoint
                    $itemPath = self::findCorrespondingItemPath($schema, $path);
                    if ($itemPath) {
                        $key = self::extractResourceName($path);
                        $endpoints[$key] = [$path, $itemPath];
                    }
                }
            }
        }

        return $endpoints;
    }

    /**
     * @param array<string, mixed> $operation
     */
    private static function isCollectionEndpoint(array $operation): bool
    {
        // Check if response schema indicates a collection
        $responses = $operation['responses'] ?? [];
        if (!\is_array($responses)) {
            return false;
        }

        $response200 = $responses['200'] ?? [];
        if (!\is_array($response200)) {
            return false;
        }

        $content = $response200['content'] ?? [];
        if (!\is_array($content)) {
            return false;
        }

        $jsonLdContent = $content['application/ld+json'] ?? [];
        if (!\is_array($jsonLdContent)) {
            return false;
        }

        $schema = $jsonLdContent['schema'] ?? [];
        if (!\is_array($schema)) {
            return false;
        }

        // Look for Hydra Collection indicators
        $properties = $schema['properties'] ?? [];
        if (\is_array($properties)) {
            $typeProperty = $properties['@type'] ?? [];
            if (\is_array($typeProperty) && isset($typeProperty['example'])
                && 'hydra:Collection' === $typeProperty['example']) {
                return true;
            }

            // Look for member property indicating collection
            if (isset($properties['member'])) {
                return true;
            }
        }

        // Check operation summary/description for collection indicators
        $summary = $operation['summary'] ?? '';
        $description = $operation['description'] ?? '';

        if (!\is_string($summary)) {
            $summary = '';
        }
        if (!\is_string($description)) {
            $description = '';
        }

        $summary = strtolower($summary);
        $description = strtolower($description);

        return str_contains($summary, 'collection')
               || str_contains($description, 'collection')
               || str_contains($summary, 'retrieves')
               || str_contains($description, 'list');
    }

    /**
     * @param array{paths: array<string, array<string, mixed>>, components: array{schemas: array<string, mixed>}} $schema
     */
    private static function findCorrespondingItemPath(array $schema, string $collectionPath): ?string
    {
        // Try to find item endpoint by adding /{id} to collection path
        $potentialItemPath = rtrim($collectionPath, '/') . '/{id}';

        if (isset($schema['paths'][$potentialItemPath]['get'])) {
            return $potentialItemPath;
        }

        // Try other common patterns
        $resourceName = self::extractResourceName($collectionPath);
        $singularSnake = rtrim($resourceName, 's');
        $singularCamel = self::snakeToCamel($singularSnake);
        $patterns = [
            $collectionPath . '/{' . $singularSnake . 'Id}',
            $collectionPath . '/{' . $singularCamel . 'Id}',
            $collectionPath . '/{' . $resourceName . 'Id}',
            str_replace($resourceName, $resourceName . '/{id}', $collectionPath),
        ];

        foreach ($patterns as $pattern) {
            if (isset($schema['paths'][$pattern]['get'])) {
                return $pattern;
            }
        }

        return null;
    }

    private static function snakeToCamel(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }

    private static function extractResourceName(string $path): string
    {
        // Extract resource name from path like "/api/watch_files" -> "watch_files"
        $segments = explode('/', trim($path, '/'));

        return end($segments);
    }

    /**
     * @param array<string, mixed> $collectionData
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractCollectionItems(array $collectionData): array
    {
        // Handle Hydra Collection format
        if (isset($collectionData['member']) && \is_array($collectionData['member'])) {
            /** @var array<int, array<string, mixed>> $member */
            $member = $collectionData['member'];

            return $member;
        }

        // For API Platform, we mainly use Hydra collections, so we don't expect direct arrays
        // but we can keep this as a safety check

        return [];
    }

    /**
     * @param array<string, mixed> $item
     */
    private function extractItemId(array $item): ?string
    {
        // Try JSON-LD @id first
        if (isset($item['@id']) && \is_string($item['@id'])) {
            // Extract ID from IRI like "/api/watch_files/12345678-1234-5678-9012-123456789012"
            if (preg_match('/\/([a-f0-9-]{36})$/', $item['@id'], $matches)) {
                return $matches[1];
            }

            // Try other ID patterns
            if (preg_match('/\/([^\/]+)$/', $item['@id'], $matches)) {
                return $matches[1];
            }
        }

        // Fallback to direct id property
        if (isset($item['id']) && \is_string($item['id'])) {
            return $item['id'];
        }

        return null;
    }

    private function createTestDataBasedOnSchema(string $collectionPath): void
    {
        // Extract resource name and create appropriate test data
        $resourceName = self::extractResourceName($collectionPath);

        // Try to intelligently create test data based on resource name
        match ($resourceName) {
            'watch_files' => $this->createTestWatchFile(),
            'users' => UserFactory::new()->defaultBasilUser()->create(),
            'sources' => $this->createTestSource(),
            'actors' => $this->createTestActor(),
            default => $this->createGenericTestData(),
        };
    }

    private function createGenericTestData(): void
    {
        // For unknown resources, try to create a WatchFile as it's often needed
        // This is a fallback - in a real implementation you might analyze the schema
        // to understand what entities need to be created
        try {
            $this->createTestWatchFile();
        } catch (\Throwable) {
            // Ignore if we can't create test data
        }
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
        return SourceFactory::new()->create();
    }

    /**
     * @param array<string, mixed> $collectionItem
     * @param array<string, mixed> $individualItem
     */
    private function compareItemProperties(
        array $collectionItem,
        array $individualItem,
        string $collectionPath,
        string $itemPath,
    ): void {
        // Get common properties (collection view might have fewer fields)
        $commonProperties = array_intersect_key($collectionItem, $individualItem);

        foreach ($commonProperties as $property => $collectionValue) {
            $individualValue = $individualItem[$property];

            // Skip JSON-LD metadata properties that might differ
            if (\in_array($property, ['@context', '@type'], true)) {
                continue;
            }

            $this->assertEquals(
                $collectionValue,
                $individualValue,
                \sprintf(
                    "Property '%s' differs between collection (%s) and item (%s) views.\nCollection value: %s\nItem value: %s",
                    $property,
                    $collectionPath,
                    $itemPath,
                    $this->formatValue($collectionValue),
                    $this->formatValue($individualValue)
                )
            );
        }

        // Verify that individual item has at least the same properties as collection item
        $missingProperties = array_diff_key($collectionItem, $individualItem);
        $this->assertEmpty(
            $missingProperties,
            \sprintf(
                'Individual item (%s) is missing properties that exist in collection (%s): %s',
                $itemPath,
                $collectionPath,
                implode(', ', array_keys($missingProperties))
            )
        );
    }

    private function formatValue(mixed $value): string
    {
        if (\is_array($value)) {
            $encoded = json_encode($value, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES);

            return false !== $encoded ? $encoded : 'JSON encoding failed';
        }

        if (\is_string($value)) {
            return "\"$value\"";
        }

        if (null === $value) {
            return 'null';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        return 'non-scalar value';
    }
}
