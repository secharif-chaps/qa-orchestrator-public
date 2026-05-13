<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality;

use App\Domain\DocumentQuality\AdblockListCategory;
use App\Domain\DocumentQuality\Exception\AdblockSourceUnavailableException;
use App\Infrastructure\DocumentQuality\EasyListSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(EasyListSource::class)]
class EasyListSourceTest extends TestCase
{
    public function testParsesPlainNetworkRules(): void
    {
        $body = <<<'EASYLIST'
            [Adblock Plus 2.0]
            ! Title: EasyList
            ! Last modified: 01 Jan 2026
            ||doubleclick.net^
            ||ads.example.com^
            ||tracker.acme.io^
            EASYLIST;

        $source = new EasyListSource(new MockHttpClient(new MockResponse($body)));
        $entries = iterator_to_array($source->fetchEntries(), false);

        $domains = array_map(static fn ($entry): string => $entry->domain, $entries);
        self::assertContains('doubleclick.net', $domains);
        self::assertContains('example.com', $domains);
        self::assertContains('acme.io', $domains);
    }

    public function testSkipsCommentsAndMetadata(): void
    {
        $body = "! header\n[Adblock Plus 2.0]\n||valid.example^\n";

        $source = new EasyListSource(new MockHttpClient(new MockResponse($body)));
        $entries = iterator_to_array($source->fetchEntries(), false);

        self::assertCount(1, $entries);
        self::assertSame('valid.example', $entries[0]->domain);
    }

    public function testSkipsCosmeticRules(): void
    {
        $body = "##.ad-banner\nexample.com##.banner\n||should-keep.example^\n";

        $source = new EasyListSource(new MockHttpClient(new MockResponse($body)));
        $entries = iterator_to_array($source->fetchEntries(), false);

        $domains = array_map(static fn ($entry): string => $entry->domain, $entries);
        self::assertSame(['should-keep.example'], $domains);
    }

    public function testSkipsOptionBearingRules(): void
    {
        // Rules with modifiers (e.g. $third-party) encode context-dependent
        // logic; they would produce false positives at document-domain
        // granularity, so the source intentionally drops them.
        $body = <<<'EASYLIST'
            ||plain.example^
            ||contextual.example^$third-party
            ||image-only.example^$image
            EASYLIST;

        $source = new EasyListSource(new MockHttpClient(new MockResponse($body)));
        $entries = iterator_to_array($source->fetchEntries(), false);

        $domains = array_map(static fn ($entry): string => $entry->domain, $entries);
        self::assertSame(['plain.example'], $domains);
    }

    public function testCategoryAppliedFromConstructor(): void
    {
        $body = "||tracker.example^\n";
        $source = new EasyListSource(
            new MockHttpClient(new MockResponse($body)),
            category: AdblockListCategory::TRACKING,
        );

        $entries = iterator_to_array($source->fetchEntries(), false);
        self::assertSame(AdblockListCategory::TRACKING, $entries[0]->category);
    }

    public function testNameIsConstant(): void
    {
        $source = new EasyListSource(new MockHttpClient());
        self::assertSame('easylist', $source->name());
    }

    public function testHttpFailureThrowsAdblockSourceUnavailable(): void
    {
        $client = new MockHttpClient(new MockResponse('error', [
            'http_code' => 502,
        ]));
        $source = new EasyListSource($client);

        $this->expectException(AdblockSourceUnavailableException::class);
        $this->expectExceptionMessageMatches('/easylist/');

        iterator_to_array($source->fetchEntries(), false);
    }
}
