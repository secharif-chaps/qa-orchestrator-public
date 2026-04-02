<?php

declare(strict_types=1);

namespace App\Tests\Utils;

use App\Domain\User\User;
use Webmozart\Assert\Assert;

trait UserTestHelperTrait
{
    private function getUserId(User $user): string
    {
        $userId = $user->getId();
        Assert::stringNotEmpty($userId, 'User ID must not be empty');

        return $userId;
    }
}
