<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect;

use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Infrastructure\Collect\SourceTypeProviderResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SourceTypeProviderResolver::class)]
class SourceTypeProviderResolverTest extends TestCase
{
    private function makeSource(SourceType $type): Source
    {
        return new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            $type,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            new Actor('Test Actor', new Organisation('Test Org', 'test-org-id')),
        );
    }

    public function testResolvesDefaultProviderWhenNoOverride(): void
    {
        $resolver = new SourceTypeProviderResolver('bakus', []);

        $source = $this->makeSource(SourceType::RSS_FEED);

        $this->assertSame('bakus', $resolver->resolve($source));
    }

    public function testResolvesOverrideForMatchingSourceType(): void
    {
        $resolver = new SourceTypeProviderResolver('bakus', [
            'rss_feed' => 'apify',
        ]);

        $source = $this->makeSource(SourceType::RSS_FEED);

        $this->assertSame('apify', $resolver->resolve($source));
    }

    public function testFallsBackToDefaultForUnmatchedSourceType(): void
    {
        $resolver = new SourceTypeProviderResolver('bakus', [
            'rss_feed' => 'apify',
        ]);

        $source = $this->makeSource(SourceType::BLOG);

        $this->assertSame('bakus', $resolver->resolve($source));
    }

    public function testResolvesCorrectOverrideAmongMultiple(): void
    {
        $resolver = new SourceTypeProviderResolver('bakus', [
            'rss_feed' => 'apify',
            'blog' => 'custom_provider',
        ]);

        $this->assertSame('apify', $resolver->resolve($this->makeSource(SourceType::RSS_FEED)));
        $this->assertSame('custom_provider', $resolver->resolve($this->makeSource(SourceType::BLOG)));
        $this->assertSame('bakus', $resolver->resolve($this->makeSource(SourceType::WEBSITE)));
    }

    /**
     * @return array<string, array{SourceType}>
     */
    public static function allSourceTypesProvider(): array
    {
        $cases = [];
        foreach (SourceType::cases() as $case) {
            $cases[$case->value] = [$case];
        }

        return $cases;
    }

    #[DataProvider('allSourceTypesProvider')]
    public function testAllSourceTypesReturnDefaultWhenNoOverrides(SourceType $sourceType): void
    {
        $resolver = new SourceTypeProviderResolver('bakus', []);

        $source = $this->makeSource($sourceType);

        $this->assertSame('bakus', $resolver->resolve($source));
    }
}
