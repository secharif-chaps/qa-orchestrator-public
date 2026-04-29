<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\NormalizerContext;
use App\Infrastructure\Collect\Apify\Normalizer\WebsiteCrawlerNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebsiteCrawlerNormalizer::class)]
class WebsiteCrawlerNormalizerTest extends TestCase
{
    private WebsiteCrawlerNormalizer $normalizer;
    private NormalizerContext $context;

    protected function setUp(): void
    {
        $this->normalizer = new WebsiteCrawlerNormalizer();
        $this->context = new NormalizerContext('apify/website-content-crawler', 'dataset-456', 0);
    }

    public function testNormalizeReturnsNullWhenUrlMissing(): void
    {
        // Arrange
        $item = [
            'crawl' => [
                'httpStatusCode' => 200,
                'loadedAt' => '2024-01-15T10:00:00Z',
            ],
            'markdown' => 'Some valid markdown content here.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeReturnsNullWhenHttpStatusMissing(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/page',
            'crawl' => [
                'loadedAt' => '2024-01-15T10:00:00Z',
            ],
            'markdown' => 'Some valid markdown content here.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeReturnsNullForErrorHttpStatus(): void
    {
        // Arrange — 404 is not in 200-299 range
        $item = [
            'url' => 'https://example.com/not-found',
            'crawl' => [
                'httpStatusCode' => 404,
            ],
            'markdown' => 'Page not found content.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeReturnsNullFor3xxStatus(): void
    {
        // Arrange — 301 redirect is not in 200-299 range
        $item = [
            'url' => 'https://example.com/redirect',
            'crawl' => [
                'httpStatusCode' => 301,
            ],
            'markdown' => 'This page has been moved.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeCreatesDocumentForValidItem(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/page',
            'title' => 'Page Title',
            'crawl' => [
                'httpStatusCode' => 200,
                'loadedAt' => '2024-03-10T08:00:00Z',
            ],
            'markdown' => '# Page Title\n\nThis is the page content in markdown format.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('https://example.com/page', $result->getUrl());
        $this->assertSame('Page Title', $result->getTitle());
        $this->assertSame('# Page Title\n\nThis is the page content in markdown format.', $result->getContent());
    }

    public function testNormalizePrefersMarkdownOverText(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/page',
            'crawl' => [
                'httpStatusCode' => 200,
            ],
            'markdown' => '# Markdown Content\n\nThis is markdown.',
            'text' => 'This is the plain text content instead.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('# Markdown Content\n\nThis is markdown.', $result->getContent());
    }

    public function testNormalizeUsesTextFallbackWhenNoMarkdown(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/page',
            'crawl' => [
                'httpStatusCode' => 200,
            ],
            'text' => 'This is the plain text content of the page.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('This is the plain text content of the page.', $result->getContent());
    }

    public function testNormalizeReturnsNullWhenContentEmpty(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/page',
            'crawl' => [
                'httpStatusCode' => 200,
            ],
            // No markdown, text or html fields
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNull($result);
    }

    public function testNormalizeParsesLoadedAtAsDateCollect(): void
    {
        // Arrange
        $item = [
            'url' => 'https://example.com/page',
            'crawl' => [
                'httpStatusCode' => 200,
                'loadedAt' => '2024-06-20T15:30:00Z',
            ],
            'markdown' => 'Valid markdown content for this test case.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('2024-06-20', $result->getDateCollect()->format('Y-m-d'));
        $this->assertSame('15:30:00', $result->getDateCollect()->format('H:i:s'));
    }

    public function testNormalizeSetsCorrectProviderId(): void
    {
        // Arrange
        $url = 'https://example.com/crawled-page';
        $item = [
            'url' => $url,
            'crawl' => [
                'httpStatusCode' => 200,
            ],
            'markdown' => 'Crawled page markdown content here.',
        ];

        // Act
        $result = $this->normalizer->normalize($item, $this->context);

        // Assert
        $this->assertNotNull($result);
        $this->assertSame('apify:apify/website-content-crawler:' . $url, $result->getProviderId());
    }

    public function testSupportsReturnsTrueForWebsiteCrawlerActor(): void
    {
        // Act & Assert
        $this->assertTrue($this->normalizer->supports('apify/website-content-crawler'));
    }

    public function testSupportsReturnsFalseForOtherActor(): void
    {
        // Act & Assert
        $this->assertFalse($this->normalizer->supports('lhotanova/google-news-scraper'));
    }
}
