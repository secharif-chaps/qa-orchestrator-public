<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\DocumentQuality;

use App\Domain\DocumentQuality\AdblockListCategory;
use App\Domain\DocumentQuality\Exception\AdblockSourceUnavailableException;
use App\Infrastructure\DocumentQuality\StevenBlackHostsSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(StevenBlackHostsSource::class)]
class StevenBlackHostsSourceTest extends TestCase
{
    public function testParsesStandardHostsFormat(): void
    {
        $body = <<<'HOSTS'
            # StevenBlack unified hosts (header comment)
            # =====================================
            127.0.0.1 localhost
            0.0.0.0 doubleclick.net
            0.0.0.0 ads.example.com
            0.0.0.0 tracking.acme.io
            HOSTS;

        $source = new StevenBlackHostsSource(new MockHttpClient(new MockResponse($body)));
        $entries = iterator_to_array($source->fetchEntries(), false);

        $domains = array_map(static fn ($entry): string => $entry->domain, $entries);
        self::assertContains('doubleclick.net', $domains);
        self::assertContains('example.com', $domains);
        self::assertContains('acme.io', $domains);
        self::assertNotContains('localhost', $domains, '127.0.0.1 localhost is dropped because localhost has no TLD');
    }

    public function testSkipsCommentsAndBlankLines(): void
    {
        $body = "\n\n# comment\n   \n0.0.0.0 valid.example\n";

        $source = new StevenBlackHostsSource(new MockHttpClient(new MockResponse($body)));
        $entries = iterator_to_array($source->fetchEntries(), false);

        self::assertCount(1, $entries);
        self::assertSame('valid.example', $entries[0]->domain);
    }

    public function testSkipsMalformedAddresses(): void
    {
        $body = "1.2.3.4 not-blackhole.example\n0.0.0.0 keep.example\n";

        $source = new StevenBlackHostsSource(new MockHttpClient(new MockResponse($body)));
        $entries = iterator_to_array($source->fetchEntries(), false);

        $domains = array_map(static fn ($entry): string => $entry->domain, $entries);
        self::assertSame(['keep.example'], $domains);
    }

    public function testStripsInlineCommentsAfterHostname(): void
    {
        $body = "0.0.0.0 evil.example #explanation here\n";

        $source = new StevenBlackHostsSource(new MockHttpClient(new MockResponse($body)));
        $entries = iterator_to_array($source->fetchEntries(), false);

        self::assertCount(1, $entries);
        self::assertSame('evil.example', $entries[0]->domain);
    }

    public function testCategoryIsAppliedFromConstructorArgument(): void
    {
        $body = "0.0.0.0 malware.example\n";
        $source = new StevenBlackHostsSource(
            new MockHttpClient(new MockResponse($body)),
            category: AdblockListCategory::MALWARE,
        );

        $entries = iterator_to_array($source->fetchEntries(), false);
        self::assertSame(AdblockListCategory::MALWARE, $entries[0]->category);
    }

    public function testNameIsConstant(): void
    {
        $source = new StevenBlackHostsSource(new MockHttpClient());
        self::assertSame('stevenblack', $source->name());
    }

    public function testHttpFailureThrowsAdblockSourceUnavailable(): void
    {
        $client = new MockHttpClient(new MockResponse('error', [
            'http_code' => 500,
        ]));
        $source = new StevenBlackHostsSource($client);

        $this->expectException(AdblockSourceUnavailableException::class);
        $this->expectExceptionMessageMatches('/stevenblack/');

        iterator_to_array($source->fetchEntries(), false);
    }
}
