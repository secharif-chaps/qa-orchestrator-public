<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Web;

use App\Application\Collect\Web\Exception\InvalidWebCollectConfigException;
use App\Application\Collect\Web\WebCollectConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebCollectConfig::class)]
class WebCollectConfigTest extends TestCase
{
    #[Test]
    public function fromCollectTaskConfigurationParsesAllFields(): void
    {
        $config = WebCollectConfig::fromCollectTaskConfiguration([
            'url' => 'https://example.com/article',
            'raw_html' => '<html>x</html>',
            'title' => 'Override',
            'excerpt' => 'Custom',
            '_sync_chain' => true,
        ]);

        self::assertSame('https://example.com/article', $config->url);
        self::assertSame('<html>x</html>', $config->rawHtml);
        self::assertSame('Override', $config->titleOverride);
        self::assertSame('Custom', $config->excerptOverride);
        self::assertTrue($config->syncChain);
    }

    #[Test]
    public function fromCollectTaskConfigurationDefaultsAbsentFieldsToNull(): void
    {
        $config = WebCollectConfig::fromCollectTaskConfiguration([
            'url' => 'https://example.com',
        ]);

        self::assertSame('https://example.com', $config->url);
        self::assertNull($config->rawHtml);
        self::assertNull($config->titleOverride);
        self::assertNull($config->excerptOverride);
        self::assertFalse($config->syncChain);
    }

    #[Test]
    public function fromCollectTaskConfigurationRejectsNonStringUrl(): void
    {
        $this->expectException(InvalidWebCollectConfigException::class);
        $this->expectExceptionMessageMatches('/url.*must be a string/');

        WebCollectConfig::fromCollectTaskConfiguration([
            'url' => 42,
        ]);
    }

    #[Test]
    public function fromCollectTaskConfigurationRejectsNonStringRawHtml(): void
    {
        $this->expectException(InvalidWebCollectConfigException::class);
        $this->expectExceptionMessageMatches('/raw_html.*must be a string/');

        WebCollectConfig::fromCollectTaskConfiguration([
            'raw_html' => ['not', 'a', 'string'],
        ]);
    }

    #[Test]
    public function validatePassesWithUrlOnly(): void
    {
        $this->expectNotToPerformAssertions();

        new WebCollectConfig(url: 'https://example.com/article')
->validate();
    }

    #[Test]
    public function validatePassesWithRawHtmlOnly(): void
    {
        $this->expectNotToPerformAssertions();

        new WebCollectConfig(rawHtml: '<html>some content</html>')
->validate();
    }

    #[Test]
    public function validateThrowsWhenBothUrlAndRawHtmlAreEmpty(): void
    {
        $config = new WebCollectConfig(titleOverride: 'just an override');

        $this->expectException(InvalidWebCollectConfigException::class);
        $this->expectExceptionMessageMatches('/url.*raw_html|raw_html.*url/');

        $config->validate();
    }

    #[Test]
    public function validateRejectsEmptyStringValues(): void
    {
        $config = new WebCollectConfig(url: '', rawHtml: '');

        $this->expectException(InvalidWebCollectConfigException::class);

        $config->validate();
    }

    #[Test]
    public function toCollectTaskConfigurationOmitsNullFields(): void
    {
        $config = new WebCollectConfig(url: 'https://example.com');

        $serialised = $config->toCollectTaskConfiguration();

        self::assertSame([
            'url' => 'https://example.com',
        ], $serialised);
        self::assertArrayNotHasKey('raw_html', $serialised);
        self::assertArrayNotHasKey('_sync_chain', $serialised);
    }

    #[Test]
    public function toCollectTaskConfigurationIncludesSyncChainOnlyWhenTrue(): void
    {
        $without = new WebCollectConfig(url: 'https://example.com', syncChain: false);
        $with = new WebCollectConfig(url: 'https://example.com', syncChain: true);

        self::assertArrayNotHasKey('_sync_chain', $without->toCollectTaskConfiguration());
        self::assertTrue($with->toCollectTaskConfiguration()['_sync_chain']);
    }

    #[Test]
    public function roundTripsThroughCollectTaskConfiguration(): void
    {
        $original = new WebCollectConfig(
            url: 'https://example.com/article',
            rawHtml: null,
            titleOverride: 'CLI Title',
            excerptOverride: 'CLI Excerpt',
            syncChain: true,
        );

        $rebuilt = WebCollectConfig::fromCollectTaskConfiguration($original->toCollectTaskConfiguration());

        self::assertEquals($original, $rebuilt);
    }
}
