<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document\Pipeline\Processor\Scoring;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\DocumentQuality\AdblockListCategory;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\Processor\Scoring\AdblockDomainProcessor;
use App\Tests\Units\Infrastructure\DocumentQuality\Stub\InMemoryAdblockDomainListProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdblockDomainProcessor::class)]
class AdblockDomainProcessorTest extends TestCase
{
    private InMemoryAdblockDomainListProvider $provider;
    private AdblockDomainProcessor $processor;

    /** @var Document&Stub */
    private Document $document;

    /** @var WatchFile&Stub */
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->provider = new InMemoryAdblockDomainListProvider();
        $this->processor = new AdblockDomainProcessor($this->provider);
        $this->document = $this->createStub(Document::class);
        $this->watchFile = $this->createStub(WatchFile::class);
    }

    public function testSupportsReturnsFalseWhenHalted(): void
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

    public function testFlaggedDomainEmitsLowSignal(): void
    {
        $this->provider->addMatch('doubleclick.net', AdblockListCategory::ADS, 'stevenblack');
        $this->document->method('getUrl')
->willReturn('https://doubleclick.net/track');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['adblock_domain'];
        self::assertSame(0.25, $signal->value);
        self::assertSame(0.8, $signal->weight);
        self::assertSame(SignalCategory::INFRASTRUCTURE_TRUST, $signal->category);
        self::assertNotNull($signal->reason);
        self::assertStringContainsString('doubleclick.net', $signal->reason->en);
        self::assertStringContainsString('stevenblack', $signal->reason->en);
    }

    public function testCleanDomainEmitsHighSignal(): void
    {
        $this->document->method('getUrl')
->willReturn('https://lemonde.fr/article');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        $signal = $result->signals['adblock_domain'];
        self::assertSame(0.85, $signal->value);
        self::assertSame(0.8, $signal->weight);
        self::assertSame(SignalCategory::INFRASTRUCTURE_TRUST, $signal->category);
        self::assertNotNull($signal->reason);
        self::assertStringContainsString('not found', strtolower($signal->reason->en));
    }

    public function testProviderFailureLeavesContextUnchanged(): void
    {
        $this->provider->failOnFindMatch();
        $this->document->method('getUrl')
->willReturn('https://example.com');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertArrayNotHasKey('adblock_domain', $result->signals);
        self::assertFalse($result->isHalted);
    }

    public function testWwwIsNormalizedBeforeLookup(): void
    {
        $this->provider->addMatch('doubleclick.net', AdblockListCategory::TRACKING);
        $this->document->method('getUrl')
->willReturn('https://www.doubleclick.net/foo');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.25, $result->signals['adblock_domain']->value);
    }

    public function testSubdomainNormalizedToRootBeforeLookup(): void
    {
        $this->provider->addMatch('tracker.com', AdblockListCategory::TRACKING);
        $this->document->method('getUrl')
->willReturn('https://stats.tracker.com/pixel');
        $context = new DocumentPipelineContext($this->document, $this->watchFile);

        $result = $this->processor->process($context);

        self::assertSame(0.25, $result->signals['adblock_domain']->value);
    }
}
