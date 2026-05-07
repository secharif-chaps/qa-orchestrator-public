<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Url;

use App\Domain\Url\Exception\UnsafeUrlException;
use App\Infrastructure\Url\PhpUrlSanitizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PhpUrlSanitizer::class)]
class PhpUrlSanitizerTest extends TestCase
{
    private PhpUrlSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new PhpUrlSanitizer();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeUrlProvider(): iterable
    {
        yield 'localhost literal' => ['http://localhost/admin'];
        yield 'IPv4 loopback' => ['http://127.0.0.1/'];
        yield 'IPv4 loopback in middle of range' => ['http://127.5.5.5/'];
        yield 'IPv4 RFC1918 10.x' => ['http://10.0.0.1/secret'];
        yield 'IPv4 RFC1918 172.16.x' => ['http://172.16.0.1/'];
        yield 'IPv4 RFC1918 172.31.x boundary' => ['http://172.31.255.255/'];
        yield 'IPv4 RFC1918 192.168.x' => ['http://192.168.1.1/'];
        yield 'IPv4 link-local' => ['http://169.254.0.1/'];
        yield 'AWS metadata literal' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'GCE metadata hostname' => ['http://metadata.google.internal/computeMetadata/v1/'];
        yield 'CGNAT 100.64.x' => ['http://100.64.0.1/'];
        yield '0.0.0.0 literal' => ['http://0.0.0.0/'];
        yield 'this network 0.x' => ['http://0.1.2.3/'];
        yield 'ftp scheme' => ['ftp://example.com/'];
        yield 'file scheme' => ['file:///etc/passwd'];
        yield 'javascript scheme' => ['javascript:alert(1)'];
        yield 'no scheme' => ['example.com/article'];
        yield 'empty host' => ['http:///path'];
    }

    #[DataProvider('unsafeUrlProvider')]
    #[Test]
    public function assertSafePublicUrlThrowsOnUnsafeInput(string $url): void
    {
        $this->expectException(UnsafeUrlException::class);

        $this->sanitizer->assertSafePublicUrl($url);
    }

    #[Test]
    public function assertSafePublicUrlAcceptsPublicHttpUrl(): void
    {
        $this->expectNotToPerformAssertions();

        $this->sanitizer->assertSafePublicUrl('https://www.example.com/article?q=1#top');
    }

    #[Test]
    public function assertSafePublicUrlAcceptsPublicHttpsUrlWithUserInfo(): void
    {
        $this->expectNotToPerformAssertions();

        // Credentials in URL should not flag the URL as unsafe — they're a
        // logging concern, not a destination concern. The public host is
        // still public.
        $this->sanitizer->assertSafePublicUrl('https://user:pass@www.example.com/article');
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function redactCredentialsProvider(): iterable
    {
        yield 'strip user pass' => [
            'https://user:pass@www.example.com/article',
            'https://www.example.com/article',
        ];
        yield 'strip user only' => ['https://user@www.example.com/article', 'https://www.example.com/article'];
        yield 'no credentials passes through' => [
            'https://www.example.com/article',
            'https://www.example.com/article',
        ];
        yield 'preserves query and fragment' => [
            'https://user:pass@www.example.com/article?q=1#anchor',
            'https://www.example.com/article?q=1#anchor',
        ];
        yield 'preserves port' => [
            'https://user:pass@www.example.com:8443/path',
            'https://www.example.com:8443/path',
        ];
        yield 'malformed url returned verbatim' => ['not a valid url', 'not a valid url'];
    }

    #[DataProvider('redactCredentialsProvider')]
    #[Test]
    public function redactCredentialsStripsUserInfo(string $input, string $expected): void
    {
        self::assertSame($expected, $this->sanitizer->redactCredentials($input));
    }
}
