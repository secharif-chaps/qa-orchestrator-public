<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class OpenApiSchemaLoader
{
    /** @var array{paths: array<string, array<string, mixed>>, components: array{schemas: array<string, mixed>}}|null */
    private static ?array $cachedSchema = null;

    /**
     * @return array{
     *     paths: array<string, array<string, mixed>>,
     *     components: array{
     *         schemas: array<string, mixed>,
     *     }
     * }
     */
    public static function loadSchema(KernelInterface $kernel): array
    {
        if (null !== self::$cachedSchema) {
            return self::$cachedSchema;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'openapi_schema_');
        if (false === $tempFile) {
            throw new \RuntimeException('Failed to create temporary file for OpenAPI schema');
        }

        try {
            $application = new Application($kernel);
            $application->setAutoExit(false);

            $input = new ArrayInput([
                'command' => 'api:openapi:export',
                '--output' => $tempFile,
            ]);

            $output = new NullOutput();
            $exitCode = $application->run($input, $output);

            if (0 !== $exitCode) {
                throw new \RuntimeException('Failed to export OpenAPI schema');
            }

            $content = file_get_contents($tempFile);
            if (false === $content) {
                throw new \RuntimeException('Failed to read OpenAPI schema file');
            }

            $schema = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            if (!\is_array($schema)) {
                throw new \RuntimeException('Invalid OpenAPI schema format');
            }

            /** @var array{paths: array<string, array<string, mixed>>, components: array{schemas: array<string, mixed>}} $schema */
            self::$cachedSchema = $schema;

            return $schema;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * @param array{
     *      paths: array<string, array<string, mixed>>,
     *      components: array{
     *          schemas: array<string, mixed>,
     *      }
     *  } $schema
     * @param list<string> $methodsAllowed
     *
     * @return \Generator<string, array{method: string, path: string, operation: array<string, mixed>}, mixed, void>
     */
    public static function getEndpoints(array $schema, array $methodsAllowed): \Generator
    {
        $paths = $schema['paths'];

        foreach ($paths as $path => $methods) {
            foreach ($methods as $method => $operation) {
                if (!\in_array(strtoupper($method), $methodsAllowed, true)) {
                    continue;
                }

                if (!\is_array($operation)) {
                    continue;
                }

                /** @var array<string, mixed> $operation */
                yield \sprintf('%s %s', strtoupper($method), $path) => [
                    'method' => strtoupper($method),
                    'path' => $path,
                    'operation' => $operation,
                ];
            }
        }
    }

    /**
     * @param array{
     *     paths: array<string, array<string, mixed>>,
     *     components: array{
     *          schemas: array<string, mixed>,
     *     }
     * } $schema
     * @param array<string, mixed> $responseData
     *
     * @return list<string>
     */
    public static function validateResponse(
        array $schema,
        string $path,
        string $method,
        int $statusCode,
        array $responseData,
    ): array {
        $errors = [];

        $operation = $schema['paths'][$path][$method] ?? null;
        if (!\is_array($operation)) {
            return ['Operation not found in schema'];
        }

        $responses = $operation['responses'] ?? null;
        if (!\is_array($responses)) {
            return ['No responses defined in schema'];
        }

        $responseSchema = $responses[(string) $statusCode] ?? null;
        if (!\is_array($responseSchema)) {
            $errors[] = \sprintf('Status code %d not defined in schema', $statusCode);

            return $errors;
        }

        // Basic validation - check if required properties exist
        $content = $responseSchema['content'] ?? null;
        if (\is_array($content)) {
            $jsonLdContent = $content['application/ld+json'] ?? null;
            if (\is_array($jsonLdContent)) {
                $contentSchema = $jsonLdContent['schema'] ?? null;
                if (\is_array($contentSchema) && isset($contentSchema['properties']) && \is_array(
                    $contentSchema['properties']
                )) {
                    /** @var array<string, mixed> $properties */
                    $properties = $contentSchema['properties'];
                    $errors = array_merge($errors, self::validateProperties($properties, $responseData, ''));
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $schemaProperties
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private static function validateProperties(array $schemaProperties, array $data, string $path): array
    {
        $errors = [];

        foreach ($schemaProperties as $propertyName => $propertySchema) {
            if (!\is_array($propertySchema)) {
                continue;
            }

            $currentPath = $path ? "{$path}.{$propertyName}" : $propertyName;

            // Check if required property exists
            if (isset($propertySchema['required']) && $propertySchema['required'] && !\array_key_exists(
                $propertyName,
                $data
            )) {
                $errors[] = "Required property '{$currentPath}' is missing";
                continue;
            }

            if (!\array_key_exists($propertyName, $data)) {
                continue;
            }

            $value = $data[$propertyName];

            // Basic type validation
            if (!isset($propertySchema['type'])) {
                continue;
            }

            $expectedType = $propertySchema['type'];
            if (!\is_string($expectedType) && !\is_array($expectedType)) {
                continue;
            }

            $actualType = self::getPhpType($value);

            // Ensure expectedType is properly typed for isTypeCompatible
            if (\is_array($expectedType)) {
                /** @var array<string> $expectedType */
                $typedExpectedType = $expectedType;
            } else {
                $typedExpectedType = $expectedType;
            }

            if (!self::isTypeCompatible($typedExpectedType, $actualType)) {
                if (\is_string($expectedType)) {
                    $errors[] = "Property '{$currentPath}' should be {$expectedType}, got {$actualType}";
                } else {
                    $errors[] = "Property '{$currentPath}' should be one of the allowed types, got {$actualType}";
                }
            }

            // Validate UUID format
            if (isset($propertySchema['format']) && 'uuid' === $propertySchema['format'] && \is_string($value)) {
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                    $errors[] = "Property '{$currentPath}' should be a valid UUID";
                }
            }

            // Recursive validation for objects
            if (\is_string($expectedType) && 'object' === $expectedType && \is_array(
                $value
            ) && isset($propertySchema['properties']) && \is_array($propertySchema['properties'])) {
                /** @var array<string, mixed> $properties */
                $properties = $propertySchema['properties'];
                /** @var array<string, mixed> $value */
                $errors = array_merge($errors, self::validateProperties($properties, $value, $currentPath));
            }

            // Validation for arrays
            if (\is_string($expectedType) && 'array' === $expectedType && \is_array($value)) {
                $items = $propertySchema['items'] ?? null;
                if (\is_array($items) && isset($items['properties']) && \is_array($items['properties'])) {
                    foreach ($value as $index => $item) {
                        if (\is_array($item)) {
                            /** @var array<string, mixed> $itemProperties */
                            $itemProperties = $items['properties'];
                            /** @var array<string, mixed> $item */
                            $errors = array_merge(
                                $errors,
                                self::validateProperties($itemProperties, $item, "{$currentPath}[{$index}]")
                            );
                        }
                    }
                }
            }
        }

        return $errors;
    }

    private static function getPhpType(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }
        if (\is_bool($value)) {
            return 'boolean';
        }
        if (\is_int($value)) {
            return 'integer';
        }
        if (\is_float($value)) {
            return 'number';
        }
        if (\is_string($value)) {
            return 'string';
        }
        if (\is_array($value)) {
            return 'array';
        }

        return 'unknown';
    }

    /**
     * @param string|array<string> $expectedType
     */
    private static function isTypeCompatible(string|array $expectedType, string $actualType): bool
    {
        if (\is_array($expectedType)) {
            // If expected type is an array, check if actual type is in the array
            return \in_array($actualType, $expectedType, true);
        }

        if ($expectedType === $actualType) {
            return true;
        }

        // Special cases
        if ('number' === $expectedType && 'integer' === $actualType) {
            return true;
        }

        if ('object' === $expectedType && 'array' === $actualType) {
            return true; // In JSON, objects are represented as associative arrays
        }

        return false;
    }

    public static function clearCache(): void
    {
        self::$cachedSchema = null;
    }
}
