<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify;

use App\Domain\Collect\Exception\ApifyConfigurationException;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Apify\ApifyInputInterpolator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApifyInputInterpolator::class)]
class ApifyInputInterpolatorTest extends TestCase
{
    private ApifyInputInterpolator $interpolator;

    protected function setUp(): void
    {
        $this->interpolator = new ApifyInputInterpolator();
    }

    public function testInterpolatesSourceUrl(): void
    {
        $source = $this->createSource('https://example.com');
        $template = [
            'startUrls' => ['{{source.url}}'],
        ];

        $result = $this->interpolator->interpolate($template, $source);

        $this->assertEquals([
            'startUrls' => ['https://example.com'],
        ], $result);
    }

    public function testInterpolatesSourceQuery(): void
    {
        $source = $this->createSource('https://example.com', query: 'python programming');
        $template = [
            'keywords' => ['{{source.query}}'],
        ];

        $result = $this->interpolator->interpolate($template, $source);

        $this->assertEquals([
            'keywords' => ['python programming'],
        ], $result);
    }

    public function testInterpolatesConfigValues(): void
    {
        $source = $this->createSource('https://example.com');
        $config = [
            'maxPages' => '100',
            'depth' => '3',
        ];
        $template = [
            'maxCrawlPages' => '{{config.maxPages}}',
            'maxCrawlDepth' => '{{config.depth}}',
        ];

        $result = $this->interpolator->interpolate($template, $source, $config);

        $this->assertEquals([
            'maxCrawlPages' => '100',
            'maxCrawlDepth' => '3',
        ], $result);
    }

    public function testInterpolatesWithDefaultValues(): void
    {
        $source = $this->createSource('https://example.com');
        $config = [];
        $template = [
            'maxPages' => '{{config.maxPages|default:50}}',
            'depth' => '{{config.depth|default:2}}',
        ];

        $result = $this->interpolator->interpolate($template, $source, $config);

        $this->assertEquals([
            'maxPages' => '50',
            'depth' => '2',
        ], $result);
    }

    public function testSourceConfigOverridesDefaults(): void
    {
        $source = $this->createSource('https://example.com');
        $config = [
            'maxPages' => '200',
        ];
        $template = [
            'maxCrawlPages' => '{{config.maxPages|default:50}}',
        ];

        $result = $this->interpolator->interpolate($template, $source, $config);

        $this->assertEquals([
            'maxCrawlPages' => '200',
        ], $result);
    }

    public function testInterpolatesNestedArrays(): void
    {
        $source = $this->createSource('https://example.com');
        $config = [];
        $template = [
            'proxyConfiguration' => [
                'useApifyProxy' => true,
                'groups' => ['{{config.proxyGroup|default:RESIDENTIAL}}'],
            ],
        ];

        $result = $this->interpolator->interpolate($template, $source, $config);

        $this->assertEquals([
            'proxyConfiguration' => [
                'useApifyProxy' => true,
                'groups' => ['RESIDENTIAL'],
            ],
        ], $result);
    }

    public function testMissingRequiredVariableThrows(): void
    {
        $this->expectException(ApifyConfigurationException::class);
        $this->expectExceptionMessage('Required template variable');

        $source = $this->createSource('https://example.com', query: null);
        $template = [
            'keywords' => ['{{source.query}}'],
        ];

        $this->interpolator->interpolate($template, $source);
    }

    public function testMissingConfigVariableWithoutDefaultThrows(): void
    {
        $this->expectException(ApifyConfigurationException::class);
        $this->expectExceptionMessage('Required template variable');

        $source = $this->createSource('https://example.com');
        $config = [];
        $template = [
            'maxPages' => '{{config.maxPages}}',  // No default
        ];

        $this->interpolator->interpolate($template, $source, $config);
    }

    public function testHandlesEmptySourceQuery(): void
    {
        $source = $this->createSource('https://example.com', query: null);
        $config = [];
        $template = [
            'keywords' => ['{{source.query|default:default-keyword}}'],
        ];

        $result = $this->interpolator->interpolate($template, $source, $config);

        $this->assertEquals([
            'keywords' => ['default-keyword'],
        ], $result);
    }

    public function testMultipleInterpolationsInSingleString(): void
    {
        $source = $this->createSource('https://example.com', query: 'test');
        $config = [
            'prefix' => 'search_',
        ];
        $template = [
            'outputKey' => '{{config.prefix}}{{source.query}}',
        ];

        $result = $this->interpolator->interpolate($template, $source, $config);

        $this->assertEquals([
            'outputKey' => 'search_test',
        ], $result);
    }

    public function testPreservesNonStringValues(): void
    {
        $source = $this->createSource('https://example.com');
        $config = [];
        $template = [
            'enabled' => true,
            'count' => 42,
            'items' => ['a', 'b', 'c'],
        ];

        $result = $this->interpolator->interpolate($template, $source, $config);

        $this->assertEquals($template, $result);
    }

    public function testInterpolatesConfigJsonValues(): void
    {
        $source = $this->createSource('https://example.com');
        $config = [
            'filters' => [
                'type' => 'article',
                'lang' => 'en',
            ],
        ];
        $template = [
            'searchFilters' => '{{config.filters}}',
        ];

        $result = $this->interpolator->interpolate($template, $source, $config);

        // JSON-encoded array becomes string
        $this->assertIsString($result['searchFilters']);
        $this->assertStringContainsString('type', $result['searchFilters']);
    }

    /**
     * Create a test source.
     */
    private function createSource(string $url, ?string $query = null): Source
    {
        $organisation = new Organisation('Test Org', 'test-org');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', $organisation);

        return new Source(
            name: 'Test Source',
            description: TranslatedText::fromArray([
                'fr' => 'Test source',
                'en' => 'Test source',
            ]),
            type: SourceType::WEBSITE,
            url: $url,
            primaryDomain: 'example.com',
            relevance: TranslatedText::fromArray([
                'fr' => 'Test',
                'en' => 'Test',
            ]),
            actor: null,
            watchFile: $watchFile,
            query: $query,
        );
    }
}
