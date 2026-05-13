<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\DocumentQuality\QualityConfig;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\Processor\Scoring\WatchFileOverrideProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(WatchFileOverrideProcessor::class)]
class WatchFileOverrideProcessorTest extends TestCase
{
    private WatchFileOverrideProcessor $processor;

    /** @var Document&Stub */
    private Document $document;

    /** @var WatchFile&Stub */
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->processor = new WatchFileOverrideProcessor();
        $this->document = $this->createStub(Document::class);
        $this->watchFile = $this->createStub(WatchFile::class);
    }

    public function testSupportsReturnsFalseWhenContextIsHalted(): void
    {
        $context = new DocumentPipelineContext(
            document: $this->document,
            watchFile: $this->watchFile,
            isHalted: true,
            haltReason: new TranslatedText('halté', 'halted'),
        );

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsFalseWhenUrlIsNull(): void
    {
        $this->document->method('getUrl')
->willReturn(null);
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        self::assertFalse($this->processor->supports($context));
    }

    public function testSupportsReturnsTrueWhenUrlPresent(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        self::assertTrue($this->processor->supports($context));
    }

    public function testBlockedDomainHaltsPipelineWithRejectedReason(): void
    {
        $this->document->method('getUrl')
->willReturn('https://blocked.example.com/article');
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig(blockedDomains: ['example.com']));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertTrue($result->isHalted);
        self::assertNotNull($result->haltReason);
        self::assertStringContainsString('example.com', $result->haltReason->fr);
        self::assertStringContainsString('bloqué', $result->haltReason->fr);
        self::assertStringContainsString('example.com', $result->haltReason->en);
    }

    public function testBlockedWildcardMatchesSubdomain(): void
    {
        $this->document->method('getUrl')
->willReturn('https://news.spam-network.tk');
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig(blockedDomains: ['*.tk']));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertTrue($result->isHalted);
    }

    public function testTrustedDomainEmitsHighSignal(): void
    {
        $this->document->method('getUrl')
->willReturn('https://www.lemonde.fr/article');
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig(trustedDomains: ['lemonde.fr']));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertFalse($result->isHalted);
        self::assertArrayHasKey('watchfile_override', $result->signals);
        $signal = $result->signals['watchfile_override'];
        self::assertSame(0.95, $signal->value);
        self::assertSame(2.0, $signal->weight);
        self::assertSame(SignalCategory::INFRASTRUCTURE_TRUST, $signal->category);
    }

    public function testTrustedWildcardMatchesSubdomain(): void
    {
        $this->document->method('getUrl')
->willReturn('https://service.gouv.fr/aide');
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig(trustedDomains: ['*.gouv.fr']));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayHasKey('watchfile_override', $result->signals);
    }

    public function testNoListsLeavesContextUnchanged(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig());
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertFalse($result->isHalted);
        self::assertArrayNotHasKey('watchfile_override', $result->signals);
    }

    public function testBlockedTakesPrecedenceOverTrusted(): void
    {
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig(trustedDomains: ['example.com'], blockedDomains: ['example.com']));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertTrue($result->isHalted);
        self::assertArrayNotHasKey('watchfile_override', $result->signals);
    }

    #[DataProvider('domainMatchingProvider')]
    public function testDomainMatching(string $url, string $pattern, bool $shouldMatch): void
    {
        $this->document->method('getUrl')
->willReturn($url);
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig(blockedDomains: [$pattern]));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame($shouldMatch, $result->isHalted);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function domainMatchingProvider(): array
    {
        return [
            'exact match' => ['https://example.com', 'example.com', true],
            'www stripped before match' => ['https://www.example.com', 'example.com', true],
            'wildcard matches subdomain' => ['https://foo.example.com', '*.example.com', true],
            'wildcard matches apex' => ['https://example.com', '*.example.com', true],
            'wildcard does not match unrelated' => ['https://other.com', '*.example.com', false],
            'unrelated domain' => ['https://other.com', 'example.com', false],
            'empty pattern ignored' => ['https://example.com', '', false],
            'pattern with uppercase' => ['https://example.com', 'EXAMPLE.COM', true],
            'pattern with leading www' => ['https://example.com', 'www.example.com', true],
            'wildcard pattern with uppercase' => ['https://foo.example.com', '*.Example.COM', true],
            'idn pattern matches punycode domain' => ['https://xn--mnchen-3ya.de', 'münchen.de', true],
        ];
    }

    public function testIdnDomainIsNormalizedBeforeMatch(): void
    {
        $this->document->method('getUrl')
->willReturn('https://münchen.de');
        $this->watchFile->method('getQualityConfig')
->willReturn(new QualityConfig(blockedDomains: ['xn--mnchen-3ya.de']));
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertTrue($result->isHalted);
    }
}
