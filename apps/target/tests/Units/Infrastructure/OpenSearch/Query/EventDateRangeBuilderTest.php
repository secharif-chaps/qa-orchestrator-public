<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Query;

use App\Infrastructure\OpenSearch\Query\EventDateRangeBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventDateRangeBuilder::class)]
final class EventDateRangeBuilderTest extends TestCase
{
    private EventDateRangeBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new EventDateRangeBuilder();
    }

    #[Test]
    public function itReturnsEmptyArrayWhenNoDatesProvided(): void
    {
        $result = $this->builder->buildClauses();

        self::assertSame([], $result);
    }

    #[Test]
    public function itBuildsClausesForEndDateOnly(): void
    {
        $endDate = new \DateTimeImmutable('2025-12-31 23:59:59');

        $result = $this->builder->buildClauses(endDate: $endDate);

        self::assertCount(1, $result);
        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);
        self::assertSame('2025-12-31T23:59:59Z', $firstClause['range']['startDate']['lte']);
    }

    #[Test]
    public function itBuildsClausesForStartDateOnly(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01 00:00:00');

        $result = $this->builder->buildClauses(startDate: $startDate);

        self::assertCount(1, $result);
        $firstClause = $result[0];
        self::assertArrayHasKey('bool', $firstClause);
        /** @phpstan-var array{bool: array<string, mixed>} $firstClause */
        self::assertArrayHasKey('should', $firstClause['bool']);
        self::assertIsArray($firstClause['bool']['should']);
        self::assertCount(2, $firstClause['bool']['should']);

        // Check the range clause for endDate >= startDate
        $firstShouldClause = $firstClause['bool']['should'][0];
        self::assertIsArray($firstShouldClause);
        self::assertArrayHasKey('range', $firstShouldClause);
        self::assertIsArray($firstShouldClause['range']);
        self::assertArrayHasKey('endDate', $firstShouldClause['range']);
        self::assertIsArray($firstShouldClause['range']['endDate']);
        self::assertSame('2025-01-01T00:00:00Z', $firstShouldClause['range']['endDate']['gte']);

        // Check the must_not exists clause for ongoing events
        $secondShouldClause = $firstClause['bool']['should'][1];
        self::assertIsArray($secondShouldClause);
        self::assertArrayHasKey('bool', $secondShouldClause);
        self::assertIsArray($secondShouldClause['bool']);
        self::assertArrayHasKey('must_not', $secondShouldClause['bool']);
        self::assertIsArray($secondShouldClause['bool']['must_not']);
        self::assertArrayHasKey('exists', $secondShouldClause['bool']['must_not']);
        self::assertIsArray($secondShouldClause['bool']['must_not']['exists']);
        self::assertSame('endDate', $secondShouldClause['bool']['must_not']['exists']['field']);

        // Check minimum_should_match
        self::assertSame(1, $firstClause['bool']['minimum_should_match']);
    }

    #[Test]
    public function itBuildsCombinedClausesForBothDates(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01 00:00:00');
        $endDate = new \DateTimeImmutable('2025-12-31 23:59:59');

        $result = $this->builder->buildClauses(startDate: $startDate, endDate: $endDate);

        self::assertCount(2, $result);

        // First clause: startDate <= endDate filter
        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);
        self::assertSame('2025-12-31T23:59:59Z', $firstClause['range']['startDate']['lte']);

        // Second clause: endDate >= startDate OR endDate is null
        $secondClause = $result[1];
        self::assertArrayHasKey('bool', $secondClause);
        /** @phpstan-var array{bool: array<string, mixed>} $secondClause */
        self::assertArrayHasKey('should', $secondClause['bool']);
        self::assertIsArray($secondClause['bool']['should']);
        self::assertIsArray($secondClause['bool']['should'][0]);
        self::assertArrayHasKey('range', $secondClause['bool']['should'][0]);
        self::assertIsArray($secondClause['bool']['should'][0]['range']);
        self::assertArrayHasKey('endDate', $secondClause['bool']['should'][0]['range']);
        self::assertIsArray($secondClause['bool']['should'][0]['range']['endDate']);
        self::assertSame('2025-01-01T00:00:00Z', $secondClause['bool']['should'][0]['range']['endDate']['gte']);
    }

    #[Test]
    public function itHandlesStringDateForEndDate(): void
    {
        $result = $this->builder->buildClauses(endDate: '2025-12-31');

        self::assertCount(1, $result);
        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);
        self::assertSame('2025-12-31T00:00:00Z', $firstClause['range']['startDate']['lte']);
    }

    #[Test]
    public function itHandlesStringDateForStartDate(): void
    {
        $result = $this->builder->buildClauses(startDate: '2025-01-01');

        self::assertCount(1, $result);
        $firstClause = $result[0];
        self::assertArrayHasKey('bool', $firstClause);
        /** @phpstan-var array{bool: array<string, mixed>} $firstClause */
        self::assertArrayHasKey('should', $firstClause['bool']);
        self::assertIsArray($firstClause['bool']['should']);
        self::assertIsArray($firstClause['bool']['should'][0]);
        self::assertArrayHasKey('range', $firstClause['bool']['should'][0]);
        self::assertIsArray($firstClause['bool']['should'][0]['range']);
        self::assertArrayHasKey('endDate', $firstClause['bool']['should'][0]['range']);
        self::assertIsArray($firstClause['bool']['should'][0]['range']['endDate']);
        self::assertSame('2025-01-01T00:00:00Z', $firstClause['bool']['should'][0]['range']['endDate']['gte']);
    }

    #[Test]
    public function itHandlesBothDatesAsStrings(): void
    {
        $result = $this->builder->buildClauses(startDate: '2025-01-01', endDate: '2025-12-31 23:59:59');

        self::assertCount(2, $result);

        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);
        self::assertSame('2025-12-31T23:59:59Z', $firstClause['range']['startDate']['lte']);

        $secondClause = $result[1];
        self::assertArrayHasKey('bool', $secondClause);
        /** @phpstan-var array{bool: array<string, mixed>} $secondClause */
        self::assertArrayHasKey('should', $secondClause['bool']);
        self::assertIsArray($secondClause['bool']['should']);
        self::assertIsArray($secondClause['bool']['should'][0]);
        self::assertArrayHasKey('range', $secondClause['bool']['should'][0]);
        self::assertIsArray($secondClause['bool']['should'][0]['range']);
        self::assertArrayHasKey('endDate', $secondClause['bool']['should'][0]['range']);
        self::assertIsArray($secondClause['bool']['should'][0]['range']['endDate']);
        self::assertSame('2025-01-01T00:00:00Z', $secondClause['bool']['should'][0]['range']['endDate']['gte']);
    }

    #[Test]
    #[DataProvider('provideComplexDateFormats')]
    public function itHandlesVariousDateFormats(string $dateString, string $expected): void
    {
        $result = $this->builder->buildClauses(endDate: $dateString);

        self::assertCount(1, $result);
        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);
        self::assertSame($expected, $firstClause['range']['startDate']['lte']);
    }

    /**
     * @return iterable<string, array{dateString: string, expected: string}>
     */
    public static function provideComplexDateFormats(): iterable
    {
        yield 'ISO 8601 date' => [
            'dateString' => '2025-06-15',
            'expected' => '2025-06-15T00:00:00Z',
        ];

        yield 'ISO 8601 datetime' => [
            'dateString' => '2025-06-15T14:30:00',
            'expected' => '2025-06-15T14:30:00Z',
        ];

        yield 'US format' => [
            'dateString' => '06/15/2025',
            'expected' => '2025-06-15T00:00:00Z',
        ];

        yield 'Relative date' => [
            'dateString' => 'yesterday',
            'expected' => new \DateTimeImmutable('yesterday')
->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    #[Test]
    public function itReturnsUnparsedStringWhenDateFormatIsInvalid(): void
    {
        $invalidDate = 'not-a-valid-date-xyz123';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Input date time has invalid format');

        $this->builder->buildClauses(endDate: $invalidDate);
    }

    #[Test]
    public function itFormatsDateTimeImmutableWithCorrectTimezone(): void
    {
        // Create a date with timezone
        $date = new \DateTimeImmutable('2025-06-15 14:30:00', new \DateTimeZone('America/New_York'));

        $result = $this->builder->buildClauses(endDate: $date);

        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);

        // The format method should convert to UTC (Z suffix)
        self::assertStringEndsWith('Z', $firstClause['range']['startDate']['lte']);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $firstClause['range']['startDate']['lte']
        );
    }

    #[Test]
    public function itBuildsCorrectStructureForOngoingEvents(): void
    {
        $startDate = new \DateTimeImmutable('2025-01-01');

        $result = $this->builder->buildClauses(startDate: $startDate);

        // Verify the structure for handling ongoing events (null endDate)
        $firstClause = $result[0];
        self::assertArrayHasKey('bool', $firstClause);
        /** @phpstan-var array{bool: array<string, mixed>} $firstClause */
        self::assertArrayHasKey('should', $firstClause['bool']);
        self::assertIsArray($firstClause['bool']['should']);

        $shouldClauses = $firstClause['bool']['should'];

        // Second should clause must check for non-existent endDate
        self::assertIsArray($shouldClauses[1]);
        $ongoingEventClause = $shouldClauses[1];
        self::assertArrayHasKey('bool', $ongoingEventClause);
        self::assertIsArray($ongoingEventClause['bool']);
        self::assertArrayHasKey('must_not', $ongoingEventClause['bool']);
        self::assertIsArray($ongoingEventClause['bool']['must_not']);
        self::assertArrayHasKey('exists', $ongoingEventClause['bool']['must_not']);
        self::assertIsArray($ongoingEventClause['bool']['must_not']['exists']);
        self::assertSame('endDate', $ongoingEventClause['bool']['must_not']['exists']['field']);
    }

    #[Test]
    public function itPreservesDatePrecision(): void
    {
        $dateWithMicroseconds = new \DateTimeImmutable('2025-06-15 14:30:45.123456');

        $result = $this->builder->buildClauses(endDate: $dateWithMicroseconds);

        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);
        // Should format to seconds precision (microseconds truncated in format)
        self::assertSame('2025-06-15T14:30:45Z', $firstClause['range']['startDate']['lte']);
    }

    #[Test]
    public function itHandlesMidnightCorrectly(): void
    {
        $midnight = new \DateTimeImmutable('2025-01-01 00:00:00');

        $result = $this->builder->buildClauses(startDate: $midnight, endDate: $midnight);

        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);
        self::assertSame('2025-01-01T00:00:00Z', $firstClause['range']['startDate']['lte']);

        $secondClause = $result[1];
        self::assertArrayHasKey('bool', $secondClause);
        /** @phpstan-var array{bool: array<string, mixed>} $secondClause */
        self::assertArrayHasKey('should', $secondClause['bool']);
        self::assertIsArray($secondClause['bool']['should']);
        self::assertIsArray($secondClause['bool']['should'][0]);
        self::assertArrayHasKey('range', $secondClause['bool']['should'][0]);
        self::assertIsArray($secondClause['bool']['should'][0]['range']);
        self::assertArrayHasKey('endDate', $secondClause['bool']['should'][0]['range']);
        self::assertIsArray($secondClause['bool']['should'][0]['range']['endDate']);
        self::assertSame('2025-01-01T00:00:00Z', $secondClause['bool']['should'][0]['range']['endDate']['gte']);
    }

    #[Test]
    public function itBuildsQueryForSameDayEventOverlap(): void
    {
        $date = new \DateTimeImmutable('2025-06-15');

        $result = $this->builder->buildClauses(startDate: $date, endDate: $date);

        // Should build both clauses even when dates are the same
        self::assertCount(2, $result);

        $firstClause = $result[0];
        self::assertArrayHasKey('range', $firstClause);
        /** @phpstan-var array{range: array<string, array<string, string>>} $firstClause */
        self::assertArrayHasKey('startDate', $firstClause['range']);
        self::assertSame('2025-06-15T00:00:00Z', $firstClause['range']['startDate']['lte']);

        $secondClause = $result[1];
        self::assertArrayHasKey('bool', $secondClause);
        /** @phpstan-var array{bool: array<string, mixed>} $secondClause */
        self::assertArrayHasKey('should', $secondClause['bool']);
        self::assertIsArray($secondClause['bool']['should']);
        self::assertIsArray($secondClause['bool']['should'][0]);
        self::assertArrayHasKey('range', $secondClause['bool']['should'][0]);
        self::assertIsArray($secondClause['bool']['should'][0]['range']);
        self::assertArrayHasKey('endDate', $secondClause['bool']['should'][0]['range']);
        self::assertIsArray($secondClause['bool']['should'][0]['range']['endDate']);
        self::assertSame('2025-06-15T00:00:00Z', $secondClause['bool']['should'][0]['range']['endDate']['gte']);
    }
}
