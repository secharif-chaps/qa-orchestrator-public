<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Shared;

use App\Domain\Shared\DomainNormalizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DomainNormalizer::class)]
class DomainNormalizerTest extends TestCase
{
    #[DataProvider('rootFromUrlProvider')]
    public function testRootFromUrl(string $url, ?string $expected): void
    {
        self::assertSame($expected, DomainNormalizer::rootFromUrl($url));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function rootFromUrlProvider(): array
    {
        return [
            'https + www stripped' => ['https://www.example.com/path', 'example.com'],
            'http base domain' => ['http://example.com', 'example.com'],
            'sub-sub-domain → registrable root' => ['https://a.b.example.com', 'example.com'],
            'co.uk ccTLD preserved' => ['https://www.bbc.co.uk/news', 'bbc.co.uk'],
            'gov.uk ccTLD preserved' => ['https://gov.uk/foo', 'gov.uk'],
            'com.au ccTLD preserved' => ['https://news.com.au', 'news.com.au'],
            'gouv.fr ccTLD preserved' => ['https://www.service-public.gouv.fr', 'service-public.gouv.fr'],
            'IDN host → Punycode' => ['https://münchen.de', 'xn--mnchen-3ya.de'],
            'no host' => ['not-a-url', null],
            'empty host' => ['http://', null],
        ];
    }

    public function testRootOnIdnHostReturnsPunycode(): void
    {
        self::assertSame('xn--mnchen-3ya.de', DomainNormalizer::root('München.de'));
    }

    public function testRootStripsLeadingWwwOnIdn(): void
    {
        self::assertSame('xn--caf-dma.fr', DomainNormalizer::root('www.café.fr'));
    }

    public function testRootReturnsNullOnEmpty(): void
    {
        self::assertNull(DomainNormalizer::root(''));
        self::assertNull(DomainNormalizer::root('   '));
    }

    #[DataProvider('tldProvider')]
    public function testTld(string $host, ?string $expected): void
    {
        self::assertSame($expected, DomainNormalizer::tld($host));
    }

    /**
     * @return array<string, array{0: string, 1: ?string}>
     */
    public static function tldProvider(): array
    {
        return [
            '.com' => ['example.com', 'com'],
            '.tk' => ['example.tk', 'tk'],
            'gov.uk multi-label' => ['service.gov.uk', 'gov.uk'],
            'co.uk multi-label' => ['bbc.co.uk', 'co.uk'],
            'gouv.fr multi-label' => ['x.gouv.fr', 'gouv.fr'],
            'edu.au multi-label' => ['monash.edu.au', 'edu.au'],
            'plain host' => ['localhost', 'localhost'],
            'empty' => ['', null],
        ];
    }
}
