<?php

namespace App\Tests\Units\Infrastructure\User;

use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException;

class NullUserGateway implements UserGatewayInterface
{
    /**
     * @var array<string, User>
     */
    private array $users = [];

    public function get(string $id): User
    {
        if (!isset($this->users[$id])) {
            throw new UserNotFoundException('User not found for id ' . $id);
        }

        return $this->users[$id];
    }

    public function getByEmail(string $email): User
    {
        foreach ($this->users as $user) {
            if ($user->getEmail() === $email) {
                return $user;
            }
        }

        throw new UserNotFoundException();
    }

    public function save(User $user): void
    {
        $this->users[$user->getId()] = $user;
    }
}
