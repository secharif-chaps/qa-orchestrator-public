<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\UsageLimit;

use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\QuotaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QuotaExceededException::class)]
class QuotaExceededExceptionTest extends TestCase
{
    public function testGetMessageReturnsTranslationKey(): void
    {
        // Verify that getMessage() always returns the same value as getTranslationKey()
        $testCases = [
            QuotaExceededException::forWatchFileOwner(30, 25),
            QuotaExceededException::forWatchFileActive(15, 10),
            QuotaExceededException::forActiveSourcesInWatchFile(55, 50),
            QuotaExceededException::forSourceCreation(105, 100),
            QuotaExceededException::forActorCreation(35, 30),
            QuotaExceededException::forDocumentQuota(10000),
        ];

        foreach ($testCases as $exception) {
            $this->assertSame($exception->getTranslationKey(), $exception->getMessage());
        }
    }

    public function testForWatchFileOwnerMessageAndMetadata(): void
    {
        $exception = QuotaExceededException::forWatchFileOwner(30, 25);

        $this->assertSame('quota.watchfile_max_owned_non_archived', $exception->getTranslationKey());
        $this->assertSame(QuotaType::WATCHFILE_MAX_OWNED_NON_ARCHIVED, $exception->quotaType());
        $this->assertSame(25, $exception->limit());
        $this->assertSame(30, $exception->current());
    }

    public function testForDocumentQuota(): void
    {
        $exception = QuotaExceededException::forDocumentQuota(10000);

        $this->assertSame('quota.document_max_per_watchfile', $exception->getTranslationKey());
        $this->assertSame(QuotaType::DOCUMENT_MAX_PER_WATCHFILE, $exception->quotaType());
        $this->assertSame(10000, $exception->limit());
        $this->assertNull($exception->current());
    }

    public function testForWatchFileActiveMessageAndMetadata(): void
    {
        $exception = QuotaExceededException::forWatchFileActive(15, 10);

        $this->assertSame('quota.watchfile_max_active_per_user', $exception->getTranslationKey());
        $this->assertSame(QuotaType::WATCHFILE_MAX_ACTIVE_PER_USER, $exception->quotaType());
        $this->assertSame(10, $exception->limit());
        $this->assertSame(15, $exception->current());
    }

    public function testForActiveSourcesInWatchFileMessageAndMetadata(): void
    {
        $exception = QuotaExceededException::forActiveSourcesInWatchFile(55, 50);

        $this->assertSame('quota.source_max_active_per_watchfile', $exception->getTranslationKey());
        $this->assertSame(QuotaType::SOURCE_MAX_ACTIVE_PER_WATCHFILE, $exception->quotaType());
        $this->assertSame(50, $exception->limit());
        $this->assertSame(55, $exception->current());
    }

    public function testForSourceCreationMessageAndMetadata(): void
    {
        $exception = QuotaExceededException::forSourceCreation(105, 100);

        $this->assertSame('quota.source_max_per_watchfile', $exception->getTranslationKey());
        $this->assertSame(QuotaType::SOURCE_MAX_PER_WATCHFILE, $exception->quotaType());
        $this->assertSame(100, $exception->limit());
        $this->assertSame(105, $exception->current());
    }

    public function testForActorCreationMessageAndMetadata(): void
    {
        $exception = QuotaExceededException::forActorCreation(35, 30);

        $this->assertSame('quota.actor_max_per_watchfile', $exception->getTranslationKey());
        $this->assertSame(QuotaType::ACTOR_MAX_PER_WATCHFILE, $exception->quotaType());
        $this->assertSame(30, $exception->limit());
        $this->assertSame(35, $exception->current());
    }
}
