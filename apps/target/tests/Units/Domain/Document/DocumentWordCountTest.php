<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Document::class)]
class DocumentWordCountTest extends TestCase
{
    private function makeDocument(string $content): Document
    {
        return new Document(
            id: 'test-doc',
            title: 'Test',
            content: $content,
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: '',
            type: 'html',
        );
    }

    public function testReturnsZeroForEmptyContent(): void
    {
        self::assertSame(0, $this->makeDocument('')->getWordCount());
    }

    #[DataProvider('latinContentProvider')]
    public function testCountsLatinWords(string $content, int $expected): void
    {
        self::assertSame($expected, $this->makeDocument($content)->getWordCount());
    }

    /** @return array<string, array{string, int}> */
    public static function latinContentProvider(): array
    {
        return [
            'single word' => ['hello', 1],
            'two words' => ['hello world', 2],
            'four words' => ['The quick brown fox', 4],
            'extra whitespace' => ['  hello   world  ', 2],
            'apostrophe in word' => ["l'intelligence artificielle", 2],
        ];
    }

    #[DataProvider('cjkContentProvider')]
    public function testCountsCjkCharactersAsIndividualWords(string $content, int $expected): void
    {
        self::assertSame($expected, $this->makeDocument($content)->getWordCount());
    }

    /** @return array<string, array{string, int}> */
    public static function cjkContentProvider(): array
    {
        return [
            'Chinese (5 Han chars)' => ['人工知能は', 5],
            'Japanese hiragana (5 chars)' => ['こんにちは', 5],
            'Japanese katakana (3 chars)' => ['アイウ', 3],
            'Korean (4 Hangul chars)' => ['인공지능', 4],
            'Mixed Han + Hiragana' => ['人工知能はすごい', 8],
        ];
    }

    #[DataProvider('mixedContentProvider')]
    public function testCountsMixedLatinAndCjkContent(string $content, int $expected): void
    {
        self::assertSame($expected, $this->makeDocument($content)->getWordCount());
    }

    /** @return array<string, array{string, int}> */
    public static function mixedContentProvider(): array
    {
        return [
            'English word + Chinese chars' => ['AI 人工知能', 5],      // 1 latin + 4 Han
            'French + Japanese hiragana' => ['bonjour こんにちは monde', 7], // 2 latin + 5 kana
            'Korean + English' => ['인공지능 is great', 6],  // 4 Hangul + 2 latin
            'Latin ref + CJK sentence' => ['GPT-4は人工知能モデルです', 11], // 1 latin (GPT-4) + 10 CJK
        ];
    }
}
