<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\WatchFile;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Organisation\OrganisationFactory;
use App\DataFixtures\Factory\User\UserFavoriteWatchFileFactory;
use App\Domain\Actor\ActorType;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<WatchFile>
 */
class WatchFileFactory extends PersistentObjectFactory
{
    /**
     * @var list<UserFavoriteWatchFileFactory>
     */
    private array $userFavorites = [];

    /**
     * @var list<WatchFileUserFactory|WatchFileUser>
     */
    private array $watchFileUsers = [];
    private ?WatchFileUserFactory $owner = null;

    public static function class(): string
    {
        return WatchFile::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     name: string,
     *     status: WatchFileStatus,
     *     userObjective: string,
     *     organisation: OrganisationFactory,
     * }
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->text(255),
            'status' => WatchFileStatus::DRAFT,
            'userObjective' => self::faker()->text(),
            'organisation' => OrganisationFactory::new(),
        ];
    }

    public function withOrganisation(Organisation $organisation): self
    {
        return $this->with([
            'organisation' => $organisation,
        ]);
    }

    public function withCreatedBy(User $user): self
    {
        return $this->with([
            'createdBy' => $user,
        ]);
    }

    public function withOwnedBy(User $user): self
    {
        return $this->withUser($user, WatchFileUserRole::OWNER);
    }

    public function withUser(User $user, WatchFileUserRole $role): self
    {
        $relation = WatchFileUserFactory::new()
            ->with([
                'watchFile' => $this,
                'user' => $user,
                'role' => $role,
            ]);

        if (WatchFileUserRole::OWNER === $role) {
            $this->owner = $relation;
        } else {
            $this->watchFileUsers[] = $relation;
        }

        return $this->with([
            'watchFileUsers' => array_filter([$this->owner, ...$this->watchFileUsers]),
        ]);
    }

    public function withUserFavorite(User $user): self
    {
        $this->userFavorites[] = UserFavoriteWatchFileFactory::new()
            ->with([
                'watchFile' => $this,
                'user' => $user,
            ]);

        return $this->with([
            'userFavorites' => $this->userFavorites,
        ]);
    }

    /**
     * @param array<int, array{label: string, type: ActorType|string, score: float, explanation?: TranslatedText}> $actors
     */
    public function withActors(array $actors): self
    {
        $watchFileActors = [];
        foreach ($actors as $actorData) {
            $actor = ActorFactory::new()
                ->with([
                    'label' => $actorData['label'],
                ]);

            $type = $actorData['type'];
            if (\is_string($type)) {
                $type = ActorType::from($type);
            }

            $watchFileActor = WatchFileActorFactory::new()
                ->with([
                    'actor' => $actor,
                    'watchFile' => $this,
                    'type' => $type,
                    'score' => $actorData['score'],
                ]);

            if (isset($actorData['explanation'])) {
                $watchFileActor = $watchFileActor->withExplanation($actorData['explanation']);
            }

            $watchFileActors[] = $watchFileActor;
        }

        return $this->with([
            'watchFileActors' => $watchFileActors,
        ]);
    }
}
