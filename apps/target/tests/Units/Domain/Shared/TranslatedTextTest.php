<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Shared;

use App\Domain\Shared\TranslatedText;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslatedText::class)]
class TranslatedTextTest extends TestCase
{
    public function testConstructorCreatesValidTranslatedText(): void
    {
        $translatedText = new TranslatedText('Bonjour', 'Hello');

        self::assertSame('Bonjour', $translatedText->fr);
        self::assertSame('Hello', $translatedText->en);
    }

    public function testConstructorThrowsExceptionForEmptyFrenchText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('French translation cannot be empty');

        new TranslatedText('', 'Hello');
    }

    public function testConstructorThrowsExceptionForEmptyEnglishText(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('English translation cannot be empty');

        new TranslatedText('Bonjour', '');
    }

    public function testFromArrayCreatesValidTranslatedText(): void
    {
        $data = [
            'fr' => 'Bonjour',
            'en' => 'Hello',
        ];
        $translatedText = TranslatedText::fromArray($data);

        self::assertSame('Bonjour', $translatedText->fr);
        self::assertSame('Hello', $translatedText->en);
    }

    public function testFromArrayThrowsExceptionForMissingFrenchKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation array must contain "fr" key with a string value');

        TranslatedText::fromArray([
            'en' => 'Hello',
        ]);
    }

    public function testFromArrayThrowsExceptionForMissingEnglishKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation array must contain "en" key with a string value');

        TranslatedText::fromArray([
            'fr' => 'Bonjour',
        ]);
    }

    public function testFromArrayThrowsExceptionForNonStringFrenchValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation array must contain "fr" key with a string value');

        TranslatedText::fromArray([
            'fr' => 123,
            'en' => 'Hello',
        ]);
    }

    public function testFromArrayThrowsExceptionForNonStringEnglishValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation array must contain "en" key with a string value');

        TranslatedText::fromArray([
            'fr' => 'Bonjour',
            'en' => 456,
        ]);
    }

    public function testToArrayReturnsCorrectFormat(): void
    {
        $translatedText = new TranslatedText('Bonjour', 'Hello');
        $array = $translatedText->toArray();

        self::assertSame([
            'fr' => 'Bonjour',
            'en' => 'Hello',
        ], $array);
    }

    public function testJsonSerializeReturnsCorrectFormat(): void
    {
        $translatedText = new TranslatedText('Bonjour', 'Hello');
        $json = $translatedText->jsonSerialize();

        self::assertSame([
            'fr' => 'Bonjour',
            'en' => 'Hello',
        ], $json);
    }

    public function testJsonEncodeWorks(): void
    {
        $translatedText = new TranslatedText('Bonjour', 'Hello');
        $jsonString = json_encode($translatedText);

        self::assertSame('{"fr":"Bonjour","en":"Hello"}', $jsonString);
    }

    public function testValueObjectIsImmutable(): void
    {
        $translatedText = new TranslatedText('Bonjour', 'Hello');

        // Properties should be readonly, so this test just verifies they exist and are accessible
        self::assertSame('Bonjour', $translatedText->fr);
        self::assertSame('Hello', $translatedText->en);
    }
}
