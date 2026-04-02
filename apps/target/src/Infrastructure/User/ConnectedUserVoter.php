<?php

declare(strict_types=1);

namespace App\Infrastructure\User;

use App\Domain\User\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, User>
 */
class ConnectedUserVoter extends Voter
{
    public const string CONNECTED_USER = 'CONNECTED_USER';

    protected function supports(string $attribute, $subject): bool
    {
        return self::CONNECTED_USER === $attribute && $subject instanceof User;
    }

    /**
     * @param User $subject
     */
    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            self::CONNECTED_USER => $this->canView($subject, $token, $vote),
            default => false,
        };
    }

    private function canView(User $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('The user is not logged in.');

            return false;
        }

        if ($subject->getId() === $user->getId()) {
            return true;
        }

        $vote?->addReason('The user is not the owner of the resource.');

        return false;
    }
}
