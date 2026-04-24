<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify;

use App\Domain\Collect\Exception\CollectException;
use App\Infrastructure\Collect\Apify\ApifyRunReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApifyRunReference::class)]
class ApifyRunReferenceTest extends TestCase
{
    public function testFromValidProviderTaskId(): void
    {
        $ref = ApifyRunReference::fromProviderTaskId('myActor~scraper:abc123def');

        $this->assertSame('myActor~scraper', $ref->apifyActorId);
        $this->assertSame('abc123def', $ref->runId);
    }

    #[DataProvider('invalidProviderTaskIdProvider')]
    public function testFromInvalidFormatThrows(string $invalidId): void
    {
        $this->expectException(CollectException::class);
        $this->expectExceptionMessage('Invalid Apify provider task ID format');

        ApifyRunReference::fromProviderTaskId($invalidId);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function invalidProviderTaskIdProvider(): \Generator
    {
        yield 'empty separator only' => [':'];
        yield 'no separator' => ['nocolon'];
        yield 'empty actor id' => [':runId'];
        yield 'empty run id' => ['actorId:'];
        yield 'empty string' => [''];
    }

    /**
     * A stray separator in the runId portion is tolerated thanks to
     * explode(':', ..., 2): actorId is taken from the first segment and
     * the rest (including any ":") is preserved as runId. The actorId is
     * normalized upstream by ApifyCollectTaskMapper so it never contains ":".
     */
    public function testStraySeparatorInRunIdIsTolerated(): void
    {
        $ref = ApifyRunReference::fromProviderTaskId('apify~scraper:run:with:colons');

        $this->assertSame('apify~scraper', $ref->apifyActorId);
        $this->assertSame('run:with:colons', $ref->runId);
    }

    public function testRoundTrip(): void
    {
        $original = new ApifyRunReference('actor123', 'run456');
        $providerTaskId = $original->toProviderTaskId();
        $parsed = ApifyRunReference::fromProviderTaskId($providerTaskId);

        $this->assertSame($original->apifyActorId, $parsed->apifyActorId);
        $this->assertSame($original->runId, $parsed->runId);
        $this->assertSame('actor123:run456', $providerTaskId);
    }

    public function testGetRunPath(): void
    {
        $ref = new ApifyRunReference('myActor', 'run789');

        $this->assertSame('/v2/acts/myActor/runs/run789', $ref->getRunPath());
    }

    public function testToProviderTaskId(): void
    {
        $ref = new ApifyRunReference('actor~id', 'runId');

        $this->assertSame('actor~id:runId', $ref->toProviderTaskId());
    }
}
