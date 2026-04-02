<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Document;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Document\DocumentSeenStatus;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DocumentSeenStatus>
 */
class DocumentSeenStatusFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DocumentSeenStatus::class;
    }

    /**
     * @return array{
     *     user: User,
     *     documentId: Uuid,
     *     watchFile: WatchFile,
     *     seenAt: \DateTimeImmutable,
     * }
     */
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::createOne(),
            'documentId' => Uuid::v4(),
            'watchFile' => WatchFileFactory::new()->create(),
            'seenAt' => new \DateTimeImmutable(),
        ];
    }

    public function withUser(User $user): self
    {
        return $this->with([
            'user' => $user,
        ]);
    }

    public function withDocumentId(string $documentId): self
    {
        return $this->with([
            'documentId' => Uuid::fromString($documentId),
        ]);
    }

    public function withWatchFile(WatchFile $watchFile): self
    {
        return $this->with([
            'watchFile' => $watchFile,
        ]);
    }

    public function withSeenAt(\DateTimeImmutable $seenAt): self
    {
        return $this->with([
            'seenAt' => $seenAt,
        ]);
    }
}
