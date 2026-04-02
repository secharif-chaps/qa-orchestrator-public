<?php

namespace App\Domain\User;

interface UserGatewayInterface
{
    public function get(string $id): User;

    public function getByEmail(string $email): User;

    public function save(User $user): void;
}
