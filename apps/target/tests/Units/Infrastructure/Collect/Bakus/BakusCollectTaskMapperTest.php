<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus;

use App\Application\Collect\Auth\GenerateCollectTaskTokenAction;
use App\Application\Collect\Collector\GetCollectorListAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\ValueObject\Collector;
use App\Domain\Collect\ValueObject\IntegerParameter;
use App\Domain\Collect\ValueObject\StringParameter;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Bakus\BakusCollectTaskMapper;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

class BakusCollectTaskMapperTest extends TestCase
{
    use EntityUtilsTrait;
    private BakusCollectTaskMapper $mapper;
    private NullMessageBus $messageBus;
    private string $callbackType = 'webhook';
    private string $callbackUrl = 'https://example.com/callback';

    protected function setUp(): void
    {
        $this->messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                // Simple JWT token generation for testing purposes
                $payload = [
                    'collect_task_id' => $action->collectTaskId,
                    'iat' => time(),
                    'exp' => time() + 3600, // 1 hour expiration
                ];

                return JWT::encode($payload, 'test-secret-key-for-jwt-minimum-32bytes!', 'HS256');
            }

            return $this->createMockCollectors();
        });

        $this->mapper = new BakusCollectTaskMapper(
            $this->callbackType,
            $this->callbackUrl,
            false,
            null,
            [],
            $this->messageBus,
        );
    }

    public function testMapCollectTaskToBakusQuerySuccess(): void
    {
        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [
                'refresh_rate' => 30,
                'api_key' => 'test-key',
            ]
        );

        $result = $this->mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertArrayHasKey('collector_modules', $result);
        $this->assertArrayHasKey('postprocess_modules', $result);
        $this->assertArrayHasKey('mode', $result);
        $this->assertArrayHasKey('callback', $result);

        $this->assertEquals('stream', $result['mode']);
        $this->assertEquals([], $result['postprocess_modules']);

        $this->assertIsArray($result['collector_modules']);
        $collectorModules = $result['collector_modules'];
        $this->assertCount(2, $collectorModules);

        // Verify collector module structure
        $this->assertIsArray($collectorModules[0]);
        $module1 = $collectorModules[0];
        $this->assertEquals('RSS Collector', $module1['name']);
        $this->assertEquals('1.0.0', $module1['version']);
        $this->assertEquals('rss', $module1['key']);
        $this->assertEquals('https://example.com/rss', $module1['value']);
        $this->assertEquals([
            'refresh_rate' => 30,
        ], $module1['parameters']);
        $this->assertNull($module1['max_cache_age']);

        $this->assertIsArray($collectorModules[1]);
        $module2 = $collectorModules[1];
        $this->assertEquals('Web Collector', $module2['name']);
        $this->assertEquals('2.1.0', $module2['version']);
        $this->assertEquals('web', $module2['key']);
        $this->assertEquals('https://example.com/rss', $module2['value']);
        $this->assertEquals([
            'api_key' => 'test-key',
        ], $module2['parameters']);
        $this->assertNull($module2['max_cache_age']);

        // Verify callback structure
        $this->assertIsArray($result['callback']);
        $callback = $result['callback'];
        $this->assertEquals($this->callbackType, $callback['type']);
        $this->assertTrue($callback['include_status_update']);
        $this->assertEquals($this->callbackUrl, $callback['url']);
        $this->assertArrayHasKey('headers', $callback);
        $this->assertIsArray($callback['headers']);
        $this->assertArrayHasKey('Authorization', $callback['headers']);

        // Verify JWT token format (actual token generation is tested in GenerateCollectTaskTokenHandler)
        $this->assertIsString($callback['headers']['Authorization']);
        $authHeader = $callback['headers']['Authorization'];
        $this->assertStringStartsWith('Bearer ', $authHeader);
    }

    public function testMapCollectTaskToBakusQueryWithOnlyOneCollector(): void
    {
        $messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                // Simple JWT token generation for testing purposes
                $payload = [
                    'collect_task_id' => $action->collectTaskId,
                    'iat' => time(),
                    'exp' => time() + 3600, // 1 hour expiration
                ];

                return JWT::encode($payload, 'test-secret-key-for-jwt-minimum-32bytes!', 'HS256');
            }

            return [$this->createMockRssCollector()];
        });

        $mapper = new BakusCollectTaskMapper($this->callbackType, $this->callbackUrl, false, null, [], $messageBus);

        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [
                'refresh_rate' => 60,
            ],
        );

        $result = $mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['collector_modules']);
        $collectorModules = $result['collector_modules'];
        $this->assertCount(1, $collectorModules);

        $this->assertIsArray($collectorModules[0]);
        $this->assertEquals('RSS Collector', $collectorModules[0]['name']);
    }

    public function testMapCollectTaskToBakusQueryWithDefaultParameters(): void
    {
        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [] // No parameters provided
        );

        $result = $this->mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['collector_modules']);
        $this->assertIsArray($result['collector_modules'][0]);
        $module = $result['collector_modules'][0];
        $this->assertEquals([], $module['parameters']); // Default value are not sent if not provided
    }

    public function testMapCollectTaskToBakusQueryWithMissingRequiredParameter(): void
    {
        $messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                // Simple JWT token generation for testing purposes
                $payload = [
                    'collect_task_id' => $action->collectTaskId,
                    'iat' => time(),
                    'exp' => time() + 3600, // 1 hour expiration
                ];

                return JWT::encode($payload, 'test-secret-key-for-jwt-minimum-32bytes!', 'HS256');
            }

            return [$this->createCollectorWithRequiredParameter()];
        });

        $mapper = new BakusCollectTaskMapper($this->callbackType, $this->callbackUrl, false, null, [], $messageBus);

        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [] // Missing required parameter
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to map collect task to Bakus query');

        $mapper->mapCollectTaskToBakusQuery($collectTask);
    }

    public function testMapCollectTaskToBakusQueryWithMissingRequiredParameterWithDefaultValue(): void
    {
        $messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                // Simple JWT token generation for testing purposes
                $payload = [
                    'collect_task_id' => $action->collectTaskId,
                    'iat' => time(),
                    'exp' => time() + 3600, // 1 hour expiration
                ];

                return JWT::encode($payload, 'test-secret-key-for-jwt-minimum-32bytes!', 'HS256');
            }

            return [$this->createCollectorWithRequiredParameter('default_value')];
        });

        $mapper = new BakusCollectTaskMapper($this->callbackType, $this->callbackUrl, false, null, [], $messageBus);

        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [] // Missing required parameter
        );

        $result = $mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['collector_modules']);
        $this->assertIsArray($result['collector_modules'][0]);
        $module = $result['collector_modules'][0];
        $this->assertEquals([
            'required_param' => 'default_value',
        ], $module['parameters']);
    }

    public function testMapCollectTaskToBakusQueryWithUnsupportedSourceType(): void
    {
        $messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                // Simple JWT token generation for testing purposes
                $payload = [
                    'collect_task_id' => $action->collectTaskId,
                    'iat' => time(),
                    'exp' => time() + 3600, // 1 hour expiration
                ];

                return JWT::encode($payload, 'test-secret-key-for-jwt-minimum-32bytes!', 'HS256');
            }

            return [$this->createMockRssCollector([SourceType::RSS_FEED])];
        }); // Only RSS support

        $mapper = new BakusCollectTaskMapper($this->callbackType, $this->callbackUrl, false, null, [], $messageBus);

        $collectTask = $this->createCollectTaskWithSource(
            SourceType::WEBSITE, // Not supported by RSS collector
            'https://example.com',
            []
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to map collect task to Bakus query: No supported collectors found for source type: website'
        );

        $mapper->mapCollectTaskToBakusQuery($collectTask);
    }

    public function testMapCollectTaskToBakusQueryWithNullCallbackUrl(): void
    {
        $mapper = new BakusCollectTaskMapper(
            $this->callbackType,
            null, // Null callback URL
            false,
            null,
            [],
            $this->messageBus,
        );

        $collectTask = $this->createCollectTaskWithSource(SourceType::RSS_FEED, 'https://example.com/rss', []);

        $result = $mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['callback']);
        $callback = $result['callback'];
        $this->assertEquals($this->callbackType, $callback['type']);
        $this->assertTrue($callback['include_status_update']);
        $this->assertArrayNotHasKey('url', $callback);
        $this->assertArrayNotHasKey('headers', $callback);
    }

    public function testCollectorCaching(): void
    {
        $messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                return 'test-token';
            }

            return $this->createMockCollectors();
        });

        $mapper = new BakusCollectTaskMapper($this->callbackType, $this->callbackUrl, false, null, [], $messageBus);

        $collectTask = $this->createCollectTaskWithSource(SourceType::RSS_FEED, 'https://example.com/rss', []);

        // Call twice to check caching
        $mapper->mapCollectTaskToBakusQuery($collectTask);
        $mapper->mapCollectTaskToBakusQuery($collectTask);

        $collectorGetters = array_filter(
            $messageBus->getDispatchedMessages(),
            static fn ($msg) => $msg instanceof GetCollectorListAction,
        );

        $this->assertCount(1, $collectorGetters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createCollectTaskWithSource(SourceType $type, string $url, array $parameters): CollectTask
    {
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id');

        $source = new Source(
            'source-name',
            TranslatedText::fromArray([
                'fr' => 'Description en français',
                'en' => 'Description in English',
            ]),
            $type,
            $url,
            'chapsvision.com',
            TranslatedText::fromArray([
                'fr' => 'Pertinence en français',
                'en' => 'Relevance in English',
            ]),
            null,
            $watchFile,
            parameters: $parameters,
        );
        $this->forcePropertyValue($source, 'source-id');

        $collectTask = new CollectTask($source, $watchFile, 'bakus');
        $this->forcePropertyValue($collectTask, 'collect-task-id');

        return $collectTask;
    }

    /**
     * @return list<Collector>
     */
    private function createMockCollectors(): array
    {
        return [$this->createMockRssCollector(), $this->createMockWebCollector()];
    }

    /**
     * @param list<SourceType> $supportedSourceTypes
     */
    private function createMockRssCollector(
        array $supportedSourceTypes = [SourceType::RSS_FEED, SourceType::WEBSITE]): Collector
    {
        return new Collector(
            name: 'RSS Collector',
            displayName: [
                'en' => 'RSS Feed Collector',
            ],
            description: [
                'en' => 'Collects data from RSS feeds',
            ],
            type: 'rss',
            version: '1.0.0',
            iconUrl: 'https://example.com/rss-icon.png',
            parameters: [
                new IntegerParameter(
                    name: 'refresh_rate',
                    label: 'Refresh Rate',
                    required: false,
                    help: [
                        'en' => 'The frequency in minutes to refresh the RSS feed.',
                        'fr' => 'La fréquence en minutes pour rafraîchir le flux RSS.',
                    ],
                    default: 15,
                    type: 'integer',
                    min: 1,
                    max: 1440
                ),
            ],
            returnTypes: ['text/xml', 'application/rss+xml'],
            supportStream: true,
            supportBatch: false,
            supportedSourceTypes: $supportedSourceTypes,
        );
    }

    private function createMockWebCollector(): Collector
    {
        return new Collector(
            name: 'Web Collector',
            displayName: [
                'en' => 'Web Page Collector',
            ],
            description: [
                'en' => 'Collects data from web pages',
            ],
            type: 'web',
            version: '2.1.0',
            iconUrl: 'https://example.com/web-icon.png',
            parameters: [
                new StringParameter(
                    name: 'api_key',
                    label: 'API Key',
                    required: false,
                    help: [
                        'en' => 'API key for authentication',
                        'fr' => 'Clé API pour l\'authentification',
                    ],
                    default: null,
                    type: 'string',
                    minLength: null,
                    maxLength: 255,
                    pattern: null
                ),
            ],
            returnTypes: ['text/html'],
            supportStream: true,
            supportBatch: true,
            supportedSourceTypes: [SourceType::RSS_FEED, SourceType::WEBSITE],
        );
    }

    private function createCollectorWithRequiredParameter(?string $defaultValue = null): Collector
    {
        return new Collector(
            name: 'Strict Collector',
            displayName: [
                'en' => 'Strict Parameter Collector',
            ],
            description: [
                'en' => 'Collector with required parameters',
            ],
            type: 'strict',
            version: '1.0.0',
            iconUrl: 'https://example.com/strict-icon.png',
            parameters: [
                new StringParameter(
                    name: 'required_param',
                    label: 'Required Parameter',
                    required: true,
                    help: [
                        'en' => 'This parameter is required',
                        'fr' => 'Ce paramètre est requis',
                    ],
                    default: $defaultValue,
                    type: 'string',
                    minLength: 1,
                    maxLength: 100,
                    pattern: null
                ),
            ],
            returnTypes: ['application/json'],
            supportStream: false,
            supportBatch: true,
            supportedSourceTypes: [SourceType::RSS_FEED],
        );
    }

    public function testMapCollectTaskToBakusQueryWithCollectorDefaultParametersFromConfig(): void
    {
        $collectorDefaultParams = [
            'RSS Collector' => [
                'refresh_rate' => 45, // Default from config
            ],
            'Web Collector' => [
                'api_key' => 'config-api-key', // Default from config
            ],
        ];

        $messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                return 'test-token';
            }

            return $this->createMockCollectors();
        });

        $mapper = new BakusCollectTaskMapper(
            $this->callbackType,
            $this->callbackUrl,
            false,
            null,
            $collectorDefaultParams,
            $messageBus,
        );

        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [] // No parameters from source
        );

        $result = $mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['collector_modules']);
        $this->assertCount(2, $result['collector_modules']);

        // RSS Collector should use default from config
        $this->assertIsArray($result['collector_modules'][0]);
        $rssModule = $result['collector_modules'][0];
        $this->assertEquals('RSS Collector', $rssModule['name']);
        $this->assertEquals([
            'refresh_rate' => 45,
        ], $rssModule['parameters']);

        // Web Collector should use default from config
        $this->assertIsArray($result['collector_modules'][1]);
        $webModule = $result['collector_modules'][1];
        $this->assertEquals('Web Collector', $webModule['name']);
        $this->assertEquals([
            'api_key' => 'config-api-key',
        ], $webModule['parameters']);
    }

    public function testMapCollectTaskToBakusQueryWithParameterPriority(): void
    {
        // Test parameter priority: source > config defaults > parameter default
        $collectorDefaultParams = [
            'RSS Collector' => [
                'refresh_rate' => 45, // Config default
            ],
        ];

        $messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                return 'test-token';
            }

            return [$this->createMockRssCollector()];
        });

        $mapper = new BakusCollectTaskMapper(
            $this->callbackType,
            $this->callbackUrl,
            false,
            null,
            $collectorDefaultParams,
            $messageBus,
        );

        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [
                'refresh_rate' => 60,
            ] // Source parameter should override config default
        );

        $result = $mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['collector_modules']);
        $this->assertIsArray($result['collector_modules'][0]);
        $module = $result['collector_modules'][0];

        // Source parameter (60) should take precedence over config default (45) and parameter default (15)
        $this->assertEquals([
            'refresh_rate' => 60,
        ], $module['parameters']);
    }

    public function testMapCollectTaskToBakusQueryWithCollectTaskNullId(): void
    {
        $collectTask = $this->createCollectTaskWithSource(SourceType::RSS_FEED, 'https://example.com/rss', []);

        // Force the ID to be null using reflection
        $reflection = new \ReflectionClass($collectTask);
        $property = $reflection->getProperty('id');
        $property->setValue($collectTask, null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Collect task ID is required for callback authentication');

        $this->mapper->mapCollectTaskToBakusQuery($collectTask);
    }

    public function testMapCollectTaskToBakusQueryVerifyCallbackStructure(): void
    {
        $collectTask = $this->createCollectTaskWithSource(SourceType::RSS_FEED, 'https://example.com/rss', []);

        $result = $this->mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['callback']);
        $callback = $result['callback'];

        // Verify all callback fields
        $this->assertEquals('webhook', $callback['type']);
        $this->assertTrue($callback['include_status_update']);
        $this->assertEquals('https://example.com/callback', $callback['url']);
        $this->assertFalse($callback['verify_ssl']); // Should be false as per code
        $this->assertArrayHasKey('headers', $callback);
        $this->assertIsArray($callback['headers']);
        $this->assertArrayHasKey('Authorization', $callback['headers']);
    }

    public function testMapCollectTaskToBakusQueryWithOptionalParametersNotProvided(): void
    {
        // Create a collector with multiple optional parameters
        $collector = new Collector(
            name: 'Optional Params Collector',
            displayName: [
                'en' => 'Collector with optional params',
            ],
            description: [
                'en' => 'Test collector',
            ],
            type: 'optional',
            version: '1.0.0',
            iconUrl: 'https://example.com/icon.png',
            parameters: [
                new StringParameter(
                    name: 'optional_param_1',
                    label: 'Optional 1',
                    required: false,
                    help: [
                        'en' => 'Optional parameter',
                    ],
                    default: null,
                    type: 'string',
                    minLength: null,
                    maxLength: 100,
                    pattern: null
                ),
                new IntegerParameter(
                    name: 'optional_param_2',
                    label: 'Optional 2',
                    required: false,
                    help: [
                        'en' => 'Another optional parameter',
                    ],
                    default: null,
                    type: 'integer',
                    min: 1,
                    max: 100
                ),
            ],
            returnTypes: ['application/json'],
            supportStream: true,
            supportBatch: false,
            supportedSourceTypes: [SourceType::RSS_FEED],
        );

        $messageBus = new NullMessageBus(function (object $action) use ($collector) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                return 'test-token';
            }

            return [$collector];
        });

        $mapper = new BakusCollectTaskMapper($this->callbackType, $this->callbackUrl, false, null, [], $messageBus);

        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [] // No parameters provided
        );

        $result = $mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['collector_modules']);
        $this->assertIsArray($result['collector_modules'][0]);
        $module = $result['collector_modules'][0];

        // Optional parameters with null default should not be included
        $this->assertEquals([], $module['parameters']);
    }

    public function testMapCollectTaskToBakusQueryWithMixedParameterSources(): void
    {
        // Test with parameters from different sources: source, config defaults, and parameter defaults
        $collectorDefaultParams = [
            'RSS Collector' => [
                'timeout' => '45s', // Config default for timeout
            ],
        ];

        $messageBus = new NullMessageBus(function (object $action) {
            if ($action instanceof GenerateCollectTaskTokenAction) {
                return 'test-token';
            }

            // Create collector with multiple parameters
            $collector = new Collector(
                name: 'RSS Collector',
                displayName: [
                    'en' => 'RSS Collector',
                ],
                description: [
                    'en' => 'Test',
                ],
                type: 'rss',
                version: '1.0.0',
                iconUrl: 'https://example.com/icon.png',
                parameters: [
                    new IntegerParameter(
                        name: 'refresh_rate',
                        label: 'Refresh Rate',
                        required: false,
                        help: [
                            'en' => 'Refresh rate',
                        ],
                        default: 15, // Parameter default, won't be used (optional param)
                        type: 'integer',
                        min: 1,
                        max: 1440
                    ),
                    new StringParameter(
                        name: 'timeout',
                        label: 'Timeout',
                        required: false,
                        help: [
                            'en' => 'Timeout',
                        ],
                        default: '30s', // Parameter default, won't be used because config has it
                        type: 'string',
                        minLength: 1,
                        maxLength: 10,
                        pattern: null
                    ),
                    new IntegerParameter(
                        name: 'max_items',
                        label: 'Max Items',
                        required: true, // Required parameter with default
                        help: [
                            'en' => 'Max items',
                        ],
                        default: 100, // This will be used (required param with default)
                        type: 'integer',
                        min: 1,
                        max: 1000
                    ),
                    new StringParameter(
                        name: 'user_agent',
                        label: 'User Agent',
                        required: false,
                        help: [
                            'en' => 'User agent',
                        ],
                        default: null, // No default
                        type: 'string',
                        minLength: 1,
                        maxLength: 255,
                        pattern: null
                    ),
                ],
                returnTypes: ['text/xml'],
                supportStream: true,
                supportBatch: false,
                supportedSourceTypes: [SourceType::RSS_FEED],
            );

            return [$collector];
        });

        $mapper = new BakusCollectTaskMapper(
            $this->callbackType,
            $this->callbackUrl,
            false,
            null,
            $collectorDefaultParams,
            $messageBus,
        );

        $collectTask = $this->createCollectTaskWithSource(
            SourceType::RSS_FEED,
            'https://example.com/rss',
            [
                'refresh_rate' => 60,
            ] // Only provide this from source
        );

        $result = $mapper->mapCollectTaskToBakusQuery($collectTask);

        $this->assertIsArray($result['collector_modules']);
        $this->assertIsArray($result['collector_modules'][0]);
        $module = $result['collector_modules'][0];

        // Parameter priority and inclusion logic:
        // - refresh_rate: from source (60) - optional param from source takes precedence
        // - timeout: from config default ('45s') - optional param from config
        // - max_items: from parameter default (100) - required param uses its default
        // - user_agent: not included (optional with null default and not in source or config)
        $this->assertEquals([
            'refresh_rate' => 60,
            'timeout' => '45s',
            'max_items' => 100,
        ], $module['parameters']);
    }
}
