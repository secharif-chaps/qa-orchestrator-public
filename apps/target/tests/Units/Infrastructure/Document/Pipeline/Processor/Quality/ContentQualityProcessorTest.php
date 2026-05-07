<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document\Pipeline\Processor\Quality;

use App\Domain\Document\Document;
use App\Domain\Document\Pipeline\DocumentPipelineContext;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\Pipeline\Processor\Quality\ContentQualityProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentQualityProcessor::class)]
final class ContentQualityProcessorTest extends TestCase
{
    private ContentQualityProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new ContentQualityProcessor();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function errorTitleProvider(): iterable
    {
        yield 'forbidden' => ['403 Forbidden'];
        yield 'not found' => ['Page Not Found'];
        yield 'captcha challenge' => ['Please verify you are human'];
        yield 'cloudflare moment' => ['Just a moment...'];
        yield 'attention required' => ['Attention Required! | Cloudflare'];
        yield 'subscribe paywall' => ['Subscribe to continue reading'];
        yield 'paywall hit' => ['Behind the paywall: members only'];
        yield 'login required' => ['Login required to view this page'];
    }

    #[DataProvider('errorTitleProvider')]
    #[Test]
    public function haltsWhenTitleMatchesErrorPattern(string $title): void
    {
        $context = $this->buildContext(
            title: $title,
            content: 'Content body that is long enough to pass the absolute floor.'
        );

        $result = $this->processor->process($context);

        self::assertTrue($result->isHalted, "Title '$title' should halt the pipeline.");
        self::assertNotNull($result->haltReason);
        self::assertStringContainsString($title, $result->haltReason->en);
    }

    #[Test]
    public function haltsWhenContentBodyIsEssentiallyEmpty(): void
    {
        $context = $this->buildContext(
            title: 'Real article title that has real words',
            content: '<html><body><script>x</script></body></html>',
        );

        $result = $this->processor->process($context);

        self::assertTrue($result->isHalted);
        self::assertStringContainsString('essentially empty', (string) $result->haltReason?->en);
    }

    #[Test]
    public function haltsWhenContentIsBareWhitespaceAfterStripTags(): void
    {
        $context = $this->buildContext(title: 'Some title', content: '<p>   </p><div>     </div>');

        $result = $this->processor->process($context);

        self::assertTrue($result->isHalted);
    }

    #[Test]
    public function passesThroughWhenContentIsLongEnoughAndTitleIsClean(): void
    {
        $context = $this->buildContext(
            title: 'A reasonable article title',
            content: str_repeat('Real article body text. ', 5),
        );

        $result = $this->processor->process($context);

        self::assertFalse($result->isHalted);
        self::assertNull($result->haltReason);
    }

    #[Test]
    public function doesNotProcessWhenContextIsAlreadyHalted(): void
    {
        $context = $this->buildContext(title: 'Whatever', content: 'Whatever long enough body content here.');
        // Pretend an upstream processor already halted (e.g. dedup).
        $halted = $context->withHalt(new \App\Domain\Shared\TranslatedText('upstream', 'upstream'));

        self::assertFalse($this->processor->supports($halted));
    }

    #[Test]
    public function doesNotProcessWhenBothTitleAndContentAreEmpty(): void
    {
        $context = $this->buildContext(title: '', content: '');

        self::assertFalse($this->processor->supports($context));
    }

    #[Test]
    public function isCaseInsensitiveOnTitlePattern(): void
    {
        $context = $this->buildContext(
            title: 'PAYWALL: members only zone',
            content: 'Content body that is long enough to pass the absolute floor.',
        );

        $result = $this->processor->process($context);

        self::assertTrue($result->isHalted);
    }

    private function buildContext(string $title, string $content): DocumentPipelineContext
    {
        $organisation = new Organisation('Org', 'org-1');
        $watchFile = new WatchFile('wf', 'objective', $organisation);

        $document = new Document(
            id: 'doc-1',
            title: $title,
            excerpt: 'irrelevant',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $content,
        );

        return new DocumentPipelineContext(document: $document, watchFile: $watchFile);
    }
}
