<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus;

use App\Domain\Collect\Exception\InvalidCollectorDefinitionException;
use App\Domain\Collect\ValueObject\BooleanParameter;
use App\Domain\Collect\ValueObject\ChoiceParameter;
use App\Domain\Collect\ValueObject\Collector;
use App\Domain\Collect\ValueObject\IntegerParameter;
use App\Domain\Collect\ValueObject\Parameter;
use App\Domain\Collect\ValueObject\StringParameter;
use App\Infrastructure\Collect\Bakus\CollectorFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CollectorFactoryTest extends TestCase
{
    private CollectorFactory $factory;

    protected function setUp(): void
    {
        $mapping = [
            'test-collector' => ['rss_feed', 'website'],
            'http-fetcher' => ['website', 'blog'],
            'edge-case-collector' => ['rss_feed'],
        ];
        $this->factory = new CollectorFactory($mapping, new NullLogger());
    }

    public function testCreateFromBakusResponseWithMinimalData(): void
    {
        $data = [
            'name' => 'test-collector',
            'version' => '1.0.0',
            'type' => 'fetcher',
        ];

        $collector = $this->factory->createFromBakusResponse($data);

        self::assertSame('test-collector', $collector->name);
        self::assertSame('1.0.0', $collector->version);
        self::assertSame('', $collector->iconUrl);
        self::assertSame([], $collector->displayName);
        self::assertSame([], $collector->description);
        self::assertSame([], $collector->parameters);
        self::assertSame([], $collector->returnTypes);
        self::assertFalse($collector->supportStream);
        self::assertTrue($collector->supportBatch);
    }

    public function testCreateFromBakusResponseWithCompleteData(): void
    {
        $data = [
            'name' => 'http-fetcher',
            'version' => '2.1.0',
            'type' => 'http-fetcher',
            'display_name' => [
                'en' => 'HTTP Fetcher',
                'fr' => 'Récupérateur HTTP',
            ],
            'description' => [
                'en' => 'Fetches data from HTTP endpoints',
                'fr' => 'Récupère des données depuis des endpoints HTTP',
            ],
            'icon_url' => 'https://example.com/icon.png',
            'return_types' => ['content', 'http_status'],
            'stream' => true,
            'batch' => false,
            'parameters' => [
                'url' => [
                    'type' => 'string',
                    'required' => true,
                    'help' => [
                        'en' => 'URL to fetch',
                        'fr' => 'URL à récupérer',
                    ],
                    'min_length' => 10,
                    'max_length' => 2000,
                    'regex' => '^https?://',
                ],
                'timeout' => [
                    'type' => 'integer',
                    'required' => false,
                    'default' => 30,
                    'min' => 1,
                    'max' => 300,
                ],
                'verify_ssl' => [
                    'type' => 'boolean',
                    'required' => false,
                    'default' => true,
                ],
                'method' => [
                    'type' => 'choice',
                    'required' => false,
                    'choices' => ['GET', 'POST', 'PUT', 'DELETE'],
                    'default' => 'GET',
                ],
            ],
        ];

        $collector = $this->factory->createFromBakusResponse($data);

        self::assertSame('http-fetcher', $collector->name);
        self::assertSame('2.1.0', $collector->version);
        self::assertSame('https://example.com/icon.png', $collector->iconUrl);
        self::assertSame([
            'en' => 'HTTP Fetcher',
            'fr' => 'Récupérateur HTTP',
        ], $collector->displayName);
        self::assertSame([
            'en' => 'Fetches data from HTTP endpoints',
            'fr' => 'Récupère des données depuis des endpoints HTTP',
        ], $collector->description);
        self::assertSame(['content', 'http_status'], $collector->returnTypes);
        self::assertTrue($collector->supportStream);
        self::assertFalse($collector->supportBatch);

        self::assertCount(4, $collector->parameters);

        // Check string parameter
        $urlParam = $collector->parameters[0];
        self::assertInstanceOf(StringParameter::class, $urlParam);
        self::assertSame('url', $urlParam->name);
        self::assertSame('URL to fetch', $urlParam->label);
        self::assertTrue($urlParam->required);
        self::assertSame('string', $urlParam->type);
        self::assertSame(10, $urlParam->minLength);
        self::assertSame(2000, $urlParam->maxLength);
        self::assertSame('^https?://', $urlParam->pattern);

        // Check integer parameter
        $timeoutParam = $collector->parameters[1];
        self::assertInstanceOf(IntegerParameter::class, $timeoutParam);
        self::assertSame('timeout', $timeoutParam->name);
        self::assertSame('timeout', $timeoutParam->label); // Fallback to parameter name
        self::assertFalse($timeoutParam->required);
        self::assertSame(30, $timeoutParam->default);
        self::assertSame(1, $timeoutParam->min);
        self::assertSame(300, $timeoutParam->max);

        // Check boolean parameter
        $verifySslParam = $collector->parameters[2];
        self::assertInstanceOf(BooleanParameter::class, $verifySslParam);
        self::assertSame('verify_ssl', $verifySslParam->name);
        self::assertTrue($verifySslParam->default);

        // Check choice parameter
        $methodParam = $collector->parameters[3];
        self::assertInstanceOf(ChoiceParameter::class, $methodParam);
        self::assertSame('method', $methodParam->name);
        self::assertSame(['GET', 'POST', 'PUT', 'DELETE'], $methodParam->choices);
        self::assertSame('GET', $methodParam->default);
    }

    public function testCreateFromBakusResponseWithInvalidParameters(): void
    {
        $data = [
            'name' => 'test-collector',
            'version' => '1.0.0',
            'type' => 'fetcher',
            'parameters' => [
                'invalid_param' => 'not_an_array',
                'missing_type' => [
                    'required' => true,
                    // Missing 'type' field
                ],
                'unknown_type' => [
                    'type' => 'unknown_type',
                    'required' => false,
                ],
            ],
        ];

        $collector = $this->factory->createFromBakusResponse($data);

        // Should create collector but skip invalid parameters
        self::assertInstanceOf(Collector::class, $collector);
        self::assertCount(1, $collector->parameters); // Only the unknown_type parameter (as generic)
    }

    public function testCreateFromBakusResponseWithUnsupportedParameterType(): void
    {
        $data = [
            'name' => 'test-collector',
            'version' => '1.0.0',
            'type' => 'fetcher',
            'parameters' => [
                'timestamp_param' => [
                    'type' => 'timestamp',
                    'required' => false,
                    'default' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $collector = $this->factory->createFromBakusResponse($data);

        self::assertCount(1, $collector->parameters);

        $param = $collector->parameters[0];
        self::assertInstanceOf(Parameter::class, $param);
        self::assertNotInstanceOf(StringParameter::class, $param);
        self::assertSame('timestamp', $param->type);
    }

    public function testCreateFromBakusResponseThrowsExceptionForMissingRequiredFields(): void
    {
        $this->expectException(InvalidCollectorDefinitionException::class);

        $data = [
            'version' => '1.0.0',
            'type' => 'fetcher',
            // Missing required 'name' field
        ];

        $this->factory->createFromBakusResponse($data);
    }

    public function testCreateFromBakusResponseThrowsExceptionForEmptyName(): void
    {
        $this->expectException(InvalidCollectorDefinitionException::class);

        $data = [
            'name' => '',
            'version' => '1.0.0',
            'type' => 'fetcher',
        ];

        $this->factory->createFromBakusResponse($data);
    }

    public function testCreateFromBakusResponseThrowsExceptionForMissingVersion(): void
    {
        $this->expectException(InvalidCollectorDefinitionException::class);

        $data = [
            'name' => 'test-collector',
            'type' => 'fetcher',
            // Missing required 'version' field
        ];

        $this->factory->createFromBakusResponse($data);
    }

    public function testCreateFromBakusResponseWithNormalizedArrays(): void
    {
        $data = [
            'name' => 'test-collector',
            'version' => '1.0.0',
            'type' => 'fetcher',
            'display_name' => [
                'en' => 'English Name',
                'fr' => '', // Empty string should be filtered out
                'de' => 'German Name',
                'es' => null, // Null should be filtered out
            ],
            'description' => [
                'en' => 'Description',
                123 => 'Invalid key', // Non-string values should be filtered
            ],
            'return_types' => ['valid', '123', 'another_valid'],
        ];

        $collector = $this->factory->createFromBakusResponse($data);

        self::assertSame([
            'en' => 'English Name',
            'de' => 'German Name',
        ], $collector->displayName);
        self::assertSame([
            'en' => 'Description',
            123 => 'Invalid key',
        ], $collector->description);
        self::assertSame(['valid', '123', 'another_valid'], $collector->returnTypes);
    }

    public function testCreateFromBakusResponseWithParameterExtractionLabel(): void
    {
        $data = [
            'name' => 'test-collector',
            'version' => '1.0.0',
            'type' => 'fetcher',
            'parameters' => [
                'with_help' => [
                    'type' => 'string',
                    'required' => true,
                    'help' => [
                        'en' => 'English help text',
                        'fr' => 'French help text',
                    ],
                ],
                'without_help' => [
                    'type' => 'string',
                    'required' => false,
                ],
            ],
        ];

        $collector = $this->factory->createFromBakusResponse($data);

        self::assertCount(2, $collector->parameters);

        // Parameter with help should use help text as label
        $withHelpParam = $collector->parameters[0];
        self::assertSame('English help text', $withHelpParam->label);
        self::assertSame([
            'en' => 'English help text',
            'fr' => 'French help text',
        ], $withHelpParam->help);

        // Parameter without help should use parameter name as label
        $withoutHelpParam = $collector->parameters[1];
        self::assertSame('without_help', $withoutHelpParam->label);
        self::assertNull($withoutHelpParam->help);
    }

    public function testCreateFromBakusResponseHandlesParameterCreationExceptions(): void
    {
        $data = [
            'name' => 'test-collector',
            'version' => '1.0.0',
            'type' => 'fetcher',
            'parameters' => [
                'problematic_param' => [
                    'type' => 'string',
                    'required' => true,
                    'min_length' => 'invalid_integer', // This might cause issues during type casting
                ],
            ],
        ];

        $collector = $this->factory->createFromBakusResponse($data);

        // The problematic parameter should still be created (type casting handled gracefully)
        self::assertCount(1, $collector->parameters);

        $param = $collector->parameters[0];
        self::assertInstanceOf(StringParameter::class, $param);
        self::assertNull($param->minLength); // Invalid value should default to null
    }

    public function testCreateFromBakusResponseWithEdgeCases(): void
    {
        $data = [
            'name' => 'edge-case-collector',
            'version' => '1.0.0',
            'type' => 'fetcher',
            'display_name' => [], // Empty array
            'description' => null, // Null value
            'icon_url' => null,
            'return_types' => null,
            'parameters' => null,
            'stream' => null,
            'batch' => null,
        ];

        $collector = $this->factory->createFromBakusResponse($data);

        self::assertSame([], $collector->displayName);
        self::assertSame([], $collector->description);
        self::assertSame('', $collector->iconUrl);
        self::assertSame([], $collector->returnTypes);
        self::assertSame([], $collector->parameters);
        self::assertFalse($collector->supportStream); // Defaults to false
        self::assertTrue($collector->supportBatch); // Defaults to true
    }
}
