<?php

declare(strict_types=1);

namespace App\Domain\WatchFile;

use App\Domain\UsageLimit\ResourceCount;
use App\Domain\User\User;

interface WatchFileGatewayInterface
{
    /**
     * @param string    $id   The ID of the WatchFile to retrieve
     * @param User|null $user optional user to enrich with the virtual properties the WatchFile
     */
    public function get(string $id, ?User $user = null): WatchFile;

    public function save(WatchFile $watchFile): void;

    public function getForUser(string $id, User $user): WatchFile;

    /**
     * @return list<string> An array of WatchFile IDs whose user has any role
     */
    public function findAllIdsByUser(User $user): array;

    /**
     * Count the number of active WatchFiles for a given user.
     *
     * @param string $userId The user ID to count active watch files for
     */
    public function countActiveByUserId(string $userId): ResourceCount;

    /**
     * Counts non-archived WatchFiles owned by a specific user.
     *
     * @param string $userId The ID of the user (owner/creator)
     *
     * @return int The count of non-archived WatchFiles owned by the user
     */
    public function countNonArchivedByOwnerId(string $userId): int;

    /**
     * Check if an actor relation already exists for this watchfile.
     *
     * Business rule: an actor can only be linked once to a watchfile,
     * regardless of type. If a link exists, we don't create another one.
     *
     * @param string $watchFileId The watchfile ID
     * @param string $actorId     The actor ID
     *
     * @return bool True if the relation exists, false otherwise
     */
    public function hasActorRelation(string $watchFileId, string $actorId): bool;
}
