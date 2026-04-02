<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Doctrine\Type;

use App\Domain\DocumentQuality\Signal;
use App\Domain\DocumentQuality\SignalCategory;
use App\Domain\Shared\TranslatedText;
use App\Infrastructure\Doctrine\Type\SignalsType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SignalsType::class)]
class SignalsTypeTest extends TestCase
{
    private SignalsType $type;
    private PostgreSQLPlatform $platform;

    protected function setUp(): void
    {
        $this->type = new SignalsType();
        $this->platform = new PostgreSQLPlatform();
    }

    public function testConvertToPHPValueWithNullReturnsEmptyArray(): void
    {
        self::assertSame([], $this->type->convertToPHPValue(null, $this->platform));
    }

    public function testConvertToPHPValueWithEmptyStringReturnsEmptyArray(): void
    {
        self::assertSame([], $this->type->convertToPHPValue('', $this->platform));
    }

    public function testConvertToPHPValueWithAlreadyAnArrayReturnsItAsIs(): void
    {
        $signal = new Signal(value: 1.0, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST);
        $signals = [
            'https' => $signal,
        ];

        self::assertSame($signals, $this->type->convertToPHPValue($signals, $this->platform));
    }

    public function testConvertToPHPValueFromJsonStringBuildsSignalObjects(): void
    {
        $json = '{"https":{"value":1.0,"weight":0.8,"category":"infrastructure_trust","reason":null}}';

        $result = $this->type->convertToPHPValue($json, $this->platform);

        self::assertArrayHasKey('https', $result);
        self::assertInstanceOf(Signal::class, $result['https']);
        self::assertSame(1.0, $result['https']->value);
        self::assertSame(0.8, $result['https']->weight);
        self::assertSame(SignalCategory::INFRASTRUCTURE_TRUST, $result['https']->category);
        self::assertNull($result['https']->reason);
    }

    public function testConvertToPHPValuePreservesTranslatedReason(): void
    {
        $json = '{"tld":{"value":0.5,"weight":1.0,"category":"source_credibility","reason":{"fr":"Domaine suspect","en":"Suspicious domain"}}}';

        $result = $this->type->convertToPHPValue($json, $this->platform);

        self::assertInstanceOf(TranslatedText::class, $result['tld']->reason);
        self::assertSame('Domaine suspect', $result['tld']->reason->fr);
        self::assertSame('Suspicious domain', $result['tld']->reason->en);
    }

    public function testConvertToDatabaseValueSerializesSignalsToJson(): void
    {
        $signals = [
            'https' => new Signal(value: 1.0, weight: 1.0, category: SignalCategory::INFRASTRUCTURE_TRUST),
        ];

        $result = $this->type->convertToDatabaseValue($signals, $this->platform);

        /** @var array<string, array{value: float, weight: float, category: string, reason: mixed}> $decoded */
        $decoded = json_decode($result, true);
        self::assertSame(1.0, $decoded['https']['value']);
        self::assertSame(1.0, $decoded['https']['weight']);
        self::assertSame('infrastructure_trust', $decoded['https']['category']);
        self::assertNull($decoded['https']['reason']);
    }

    public function testConvertToDatabaseValueSerializesTranslatedReason(): void
    {
        $signals = [
            'tld' => new Signal(
                value: 0.5,
                weight: 1.0,
                category: SignalCategory::SOURCE_CREDIBILITY,
                reason: new TranslatedText(fr: 'Domaine suspect', en: 'Suspicious domain'),
            ),
        ];

        $result = $this->type->convertToDatabaseValue($signals, $this->platform);

        /** @var array<string, array{reason: array{fr: string, en: string}}> $decoded */
        $decoded = json_decode($result, true);
        self::assertSame('Domaine suspect', $decoded['tld']['reason']['fr']);
        self::assertSame('Suspicious domain', $decoded['tld']['reason']['en']);
    }

    public function testRoundTripPreservesAllSignalData(): void
    {
        $original = [
            'https' => new Signal(value: 1.0, weight: 0.8, category: SignalCategory::INFRASTRUCTURE_TRUST),
            'word_count' => new Signal(
                value: 0.6,
                weight: 1.0,
                category: SignalCategory::CONTENT_QUALITY,
                reason: new TranslatedText(fr: 'Contenu court', en: 'Short content'),
            ),
        ];

        $json = $this->type->convertToDatabaseValue($original, $this->platform);
        $result = $this->type->convertToPHPValue($json, $this->platform);

        self::assertCount(2, $result);
        self::assertSame(1.0, $result['https']->value);
        self::assertSame(0.8, $result['https']->weight);
        self::assertSame(SignalCategory::INFRASTRUCTURE_TRUST, $result['https']->category);
        self::assertSame(0.6, $result['word_count']->value);
        self::assertSame(SignalCategory::CONTENT_QUALITY, $result['word_count']->category);
        self::assertSame('Short content', $result['word_count']->reason?->en);
    }
}
