<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify\Normalizer;

use App\Domain\Collect\CollectTask;
use App\Domain\Source\Source;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Apify\Normalizer\ApifyNormalizerResolver;
use App\Infrastructure\Collect\Apify\Normalizer\GenericApifyNormalizer;
use App\Infrastructure\Collect\Apify\Normalizer\GoogleNewsNormalizer;
use App\Infrastructure\Collect\Apify\Normalizer\WebsiteCrawlerNormalizer;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApifyNormalizerResolver::class)]
class ApifyNormalizerResolverTest extends TestCase
{
    use EntityUtilsTrait;
    private GenericApifyNormalizer $fallback;
    private GoogleNewsNormalizer $googleNewsNormalizer;
    private WebsiteCrawlerNormalizer $websiteCrawlerNormalizer;

    protected function setUp(): void
    {
        $this->fallback = new GenericApifyNormalizer();
        $this->googleNewsNormalizer = new GoogleNewsNormalizer();
        $this->websiteCrawlerNormalizer = new WebsiteCrawlerNormalizer();
    }

    /**
     * Builds an ApifyNormalizerResolver with the standard normalizers list.
     */
    private function buildResolver(): ApifyNormalizerResolver
    {
        return new ApifyNormalizerResolver(
            normalizers: [$this->googleNewsNormalizer, $this->websiteCrawlerNormalizer],
            fallback: $this->fallback,
        );
    }

    /**
     * Creates a CollectTask with providerTaskId set via reflection,
     * since Source and WatchFile are complex domain entities.
     */
    private function createCollectTaskWithProviderTaskId(?string $providerTaskId): CollectTask
    {
        $source = $this->createStub(Source::class);
        $watchFile = $this->createStub(WatchFile::class);

        $collectTask = new CollectTask(source: $source, watchFile: $watchFile, providerName: 'apify');

        $this->forcePropertyValue($collectTask, $providerTaskId, 'providerTaskId');

        return $collectTask;
    }

    public function testReturnsMatchingNormalizerForKnownActorId(): void
    {
        // Arrange — providerTaskId matches the GoogleNewsNormalizer actor type
        $collectTask = $this->createCollectTaskWithProviderTaskId('lhotanova/google-news-scraper:run123');
        $resolver = $this->buildResolver();

        // Act
        $resolved = $resolver->resolveFor($collectTask);

        // Assert
        $this->assertInstanceOf(GoogleNewsNormalizer::class, $resolved);
    }

    public function testReturnsFallbackForUnknownActorId(): void
    {
        // Arrange — actor ID does not match any registered normalizer
        $collectTask = $this->createCollectTaskWithProviderTaskId('unknown/actor:run123');
        $resolver = $this->buildResolver();

        // Act
        $resolved = $resolver->resolveFor($collectTask);

        // Assert
        $this->assertInstanceOf(GenericApifyNormalizer::class, $resolved);
        $this->assertSame($this->fallback, $resolved);
    }

    public function testReturnsFallbackWhenProviderTaskIdIsNull(): void
    {
        // Arrange — providerTaskId is null
        $collectTask = $this->createCollectTaskWithProviderTaskId(null);
        $resolver = $this->buildResolver();

        // Act
        $resolved = $resolver->resolveFor($collectTask);

        // Assert
        $this->assertInstanceOf(GenericApifyNormalizer::class, $resolved);
        $this->assertSame($this->fallback, $resolved);
    }

    public function testReturnsFallbackWhenProviderTaskIdHasNoColon(): void
    {
        // Arrange — malformed providerTaskId with no colon separator
        $collectTask = $this->createCollectTaskWithProviderTaskId('nocolonseparator');
        $resolver = $this->buildResolver();

        // Act
        $resolved = $resolver->resolveFor($collectTask);

        // Assert
        $this->assertInstanceOf(GenericApifyNormalizer::class, $resolved);
        $this->assertSame($this->fallback, $resolved);
    }
}
