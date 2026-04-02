<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\AI;

use App\Domain\Shared\TranslatedText;
use App\Infrastructure\AI\LlmOutputSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LlmOutputSanitizerTest extends TestCase
{
    private LlmOutputSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new LlmOutputSanitizer();
    }

    #[DataProvider('sanitizeStringProvider')]
    public function testSanitize(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->sanitizer->sanitize($input));
    }

    /**
     * @return iterable<string, array{input: string, expected: string}>
     */
    public static function sanitizeStringProvider(): iterable
    {
        yield 'plain text unchanged' => [
            'input' => 'Hello World',
            'expected' => 'Hello World',
        ];

        yield 'single backtick wrapped' => [
            'input' => '`Hello World`',
            'expected' => 'Hello World',
        ];

        yield 'double backtick wrapped' => [
            'input' => '``Hello World``',
            'expected' => 'Hello World',
        ];

        yield 'triple backtick wrapped' => [
            'input' => '```Hello World```',
            'expected' => 'Hello World',
        ];

        yield 'leading whitespace with backticks' => [
            'input' => '  `Hello World`  ',
            'expected' => 'Hello World',
        ];

        yield 'multiline text with backticks' => [
            'input' => "`Hello\nWorld`",
            'expected' => "Hello\nWorld",
        ];

        yield 'only leading backtick - no change' => [
            'input' => '`Hello World',
            'expected' => '`Hello World',
        ];

        yield 'only trailing backtick - no change' => [
            'input' => 'Hello World`',
            'expected' => 'Hello World`',
        ];

        yield 'backticks in middle preserved' => [
            'input' => 'Hello `code` World',
            'expected' => 'Hello `code` World',
        ];

        yield 'code block inside text preserved' => [
            'input' => 'Some text ```code block``` more text',
            'expected' => 'Some text ```code block``` more text',
        ];

        yield 'empty string' => [
            'input' => '',
            'expected' => '',
        ];

        yield 'only backticks - extracts empty content' => [
            'input' => '```',
            'expected' => '`',
        ];

        yield 'asymmetric backticks - more at start' => [
            'input' => '```text`',
            'expected' => 'text',
        ];

        yield 'asymmetric backticks - more at end' => [
            'input' => '`text```',
            'expected' => 'text',
        ];
    }

    public function testSanitizeTranslatedText(): void
    {
        $input = new TranslatedText(fr: '`Bonjour le monde`', en: '```Hello World```');

        $result = $this->sanitizer->sanitizeTranslatedText($input);

        $this->assertSame('Bonjour le monde', $result->fr);
        $this->assertSame('Hello World', $result->en);
    }

    public function testSanitizeTranslatedTextPreservesCleanText(): void
    {
        $input = new TranslatedText(fr: 'Bonjour le monde', en: 'Hello World');

        $result = $this->sanitizer->sanitizeTranslatedText($input);

        $this->assertSame('Bonjour le monde', $result->fr);
        $this->assertSame('Hello World', $result->en);
    }

    public function testSanitizeTranslatedTextReturnsNewInstance(): void
    {
        $input = new TranslatedText(fr: '`Test FR`', en: '`Test EN`');

        $result = $this->sanitizer->sanitizeTranslatedText($input);

        $this->assertNotSame($input, $result);
    }
}
