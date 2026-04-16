<?php

namespace App\Infrastructure\WatchFile\Voter;

use App\Domain\Shared\HasWatchFileInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileUserNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileSecurity;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserGatewayInterface;
use App\Domain\WatchFile\WatchFileUserRole;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, WatchFile|HasWatchFileInterface>
 */
class WatchFileVoter extends Voter
{
    /**
     * @deprecated Use WatchFileSecurity::EDIT instead
     */
    public const string EDIT = WatchFileSecurity::EDIT;

    /**
     * @deprecated Use WatchFileSecurity::VIEW instead
     */
    public const string VIEW = WatchFileSecurity::VIEW;

    public function __construct(
        private readonly WatchFileUserGatewayInterface $watchFileUserGateway,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [WatchFileSecurity::EDIT, WatchFileSecurity::VIEW], true)) {
            return false;
        }

        return $subject instanceof WatchFile
            || $subject instanceof HasWatchFileInterface;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();

        if (!$user instanceof User) {
            $vote?->addReason('The user is not logged in.');

            return false;
        }

        $watchFile = $this->extractWatchFile($subject, $vote);

        if (null === $watchFile) {
            return false;
        }

        // Admin users have full access to all watch files
        $roles = $user->getRoles();
        if (\in_array('ROLE_ADMIN', $roles, true) || \in_array('admin', $roles, true)) {
            return true;
        }

        try {
            $watchFileUser = $this->watchFileUserGateway->getByWatchFileAndUser($watchFile, $user);

            return match ($attribute) {
                WatchFileSecurity::VIEW => true,
                WatchFileSecurity::EDIT => $this->canEdit($watchFileUser, $vote),
                default => throw new \LogicException('Unknown attribute: ' . $attribute),
            };
        } catch (WatchFileUserNotFoundException) {
            $vote?->addReason('The user does not have access to this watch file.');

            return false;
        }
    }

    private function extractWatchFile(mixed $subject, ?Vote $vote = null): ?WatchFile
    {
        if ($subject instanceof WatchFile) {
            return $subject;
        }

        if ($subject instanceof HasWatchFileInterface) {
            $watchFile = $subject->getWatchFile();

            if (null === $watchFile) {
                $vote?->addReason('The subject does not have an associated watch file.');

                return null;
            }

            return $watchFile;
        }

        $vote?->addReason('The subject is not supported by this voter.');

        return null;
    }

    private function canEdit(WatchFileUser $watchFileUser, ?Vote $vote = null): bool
    {
        if (
            WatchFileUserRole::EDITOR === $watchFileUser->getRole()
            || WatchFileUserRole::OWNER === $watchFileUser->getRole()
        ) {
            return true;
        }

        $vote?->addReason('The user does not have edit permissions for this watch file.');

        return false;
    }
}
