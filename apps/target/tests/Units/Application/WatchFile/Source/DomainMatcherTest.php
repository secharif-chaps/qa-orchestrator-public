<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Source;

use App\Domain\Source\DomainMatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainMatcher::class)]
class DomainMatcherTest extends TestCase
{
    private DomainMatcher $domainMatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domainMatcher = new DomainMatcher();
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function normalizeDomainProvider(): array
    {
        return [
            'lowercase domain' => ['acme.com', 'acme.com'],
            'uppercase domain' => ['ACME.COM', 'acme.com'],
            'mixed case domain' => ['Acme.Com', 'acme.com'],
            'domain with www prefix' => ['www.acme.com', 'acme.com'],
            'uppercase www prefix' => ['WWW.acme.com', 'acme.com'],
            'domain with www prefix uppercase' => ['www.ACME.COM', 'acme.com'],
            'domain without www' => ['acme.com', 'acme.com'],
            'empty string' => ['', null],
            'whitespace only' => ['   ', null],
            'null input' => [null, null],
            'www only' => ['www.', null],
            'domain with trailing whitespace' => ['  acme.com  ', 'acme.com'],
            'domain with leading whitespace' => ['  www.acme.com  ', 'acme.com'],
        ];
    }

    #[DataProvider('normalizeDomainProvider')]
    public function testNormalizeDomain(?string $input, ?string $expected): void
    {
        $result = $this->domainMatcher->normalizeDomain($input);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function extractDomainFromUrlProvider(): array
    {
        return [
            'https URL' => ['https://acme.com/path', 'acme.com'],
            'http URL' => ['http://acme.com/path', 'acme.com'],
            'URL with www' => ['https://www.acme.com/path', 'www.acme.com'],
            'URL with query string' => ['https://acme.com/path?query=value', 'acme.com'],
            'URL with fragment' => ['https://acme.com/path#fragment', 'acme.com'],
            'URL with port' => ['https://acme.com:8080/path', 'acme.com'],
            'URL with subdomain' => ['https://blog.acme.com/path', 'blog.acme.com'],
            'URL without path' => ['https://acme.com', 'acme.com'],
            'URL with trailing slash' => ['https://acme.com/', 'acme.com'],
            'domain only (no protocol)' => ['acme.com', 'acme.com'],
            'www domain only (no protocol)' => ['www.acme.com', 'www.acme.com'],
            'domain with path (no protocol)' => ['acme.com/path', 'acme.com'],
            'empty string' => ['', null],
            'whitespace only' => ['   ', null],
            'malformed URL' => ['not-a-url', null],
            'URL with user info' => ['https://user:pass@acme.com/path', 'acme.com'],
        ];
    }

    #[DataProvider('extractDomainFromUrlProvider')]
    public function testExtractDomainFromUrl(string $url, ?string $expected): void
    {
        $result = $this->domainMatcher->extractDomainFromUrl($url);
        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: bool}>
     */
    public static function domainsMatchProvider(): array
    {
        return [
            'exact match' => ['acme.com', 'acme.com', true],
            'case insensitive match' => ['Acme.Com', 'acme.com', true],
            'www prefix match (domain1 has www)' => ['www.acme.com', 'acme.com', true],
            'www prefix match (domain2 has www)' => ['acme.com', 'www.acme.com', true],
            'both have www' => ['www.acme.com', 'www.acme.com', true],
            'case insensitive with www' => ['WWW.Acme.Com', 'www.acme.com', true],
            'no match different domains' => ['acme.com', 'example.com', false],
            'no match subdomain' => ['blog.acme.com', 'acme.com', false],
            'no match one null' => ['acme.com', null, false],
            'no match both null' => [null, null, false],
            'no match one empty' => ['acme.com', '', false],
            'no match both empty' => ['', '', false],
            'no match whitespace' => ['acme.com', '   ', false],
        ];
    }

    #[DataProvider('domainsMatchProvider')]
    public function testDomainsMatch(?string $domain1, ?string $domain2, bool $expected): void
    {
        $result = $this->domainMatcher->domainsMatch($domain1, $domain2);
        $this->assertSame($expected, $result);
    }

    public function testNormalizeDomainStripsWwwPrefix(): void
    {
        $this->assertSame('acme.com', $this->domainMatcher->normalizeDomain('www.acme.com'));
        $this->assertSame('acme.com', $this->domainMatcher->normalizeDomain('WWW.acme.com'));
    }

    public function testNormalizeDomainIsCaseInsensitive(): void
    {
        $this->assertSame('acme.com', $this->domainMatcher->normalizeDomain('ACME.COM'));
        $this->assertSame('acme.com', $this->domainMatcher->normalizeDomain('Acme.Com'));
    }

    public function testExtractDomainFromUrlHandlesVariousFormats(): void
    {
        $this->assertSame('example.com', $this->domainMatcher->extractDomainFromUrl('https://example.com'));
        $this->assertSame('example.com', $this->domainMatcher->extractDomainFromUrl('http://example.com/path'));
        $this->assertSame('example.com', $this->domainMatcher->extractDomainFromUrl('example.com'));
    }

    public function testDomainsMatchHandlesWwwVariations(): void
    {
        // Test that www variations match
        $this->assertTrue($this->domainMatcher->domainsMatch('www.acme.com', 'acme.com'));
        $this->assertTrue($this->domainMatcher->domainsMatch('acme.com', 'www.acme.com'));
        $this->assertTrue($this->domainMatcher->domainsMatch('WWW.acme.com', 'acme.com'));
    }
}
