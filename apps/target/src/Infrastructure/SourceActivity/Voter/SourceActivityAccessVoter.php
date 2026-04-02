<?php

declare(strict_types=1);

namespace App\Infrastructure\SourceActivity\Voter;

use App\Domain\Source\SourceGatewayInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFileSecurity;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Voter that checks WATCH_FILE_VIEW permission for SourceActivity endpoints.
 *
 * For GetCollection operations, the `object` in API Platform security expressions
 * is not resolved from uriVariables. This voter extracts the sourceId from the
 * request, loads the Source, and delegates to the WatchFile voter.
 *
 * @extends Voter<string, null>
 */
class SourceActivityAccessVoter extends Voter
{
    public const string VIEW = 'SOURCE_ACTIVITY_VIEW';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SourceGatewayInterface $sourceGateway,
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && null === $subject;
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

        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            $vote?->addReason('No request available.');

            return false;
        }

        $sourceId = $request->attributes->getString('sourceId');

        if ('' === $sourceId) {
            $vote?->addReason('No source ID in request.');

            return false;
        }

        try {
            $source = $this->sourceGateway->get($sourceId);
        } catch (\Exception) {
            $vote?->addReason('Source not found.');

            return false;
        }

        return $this->security->isGranted(WatchFileSecurity::VIEW, $source->getWatchFile());
    }
}
